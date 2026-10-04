<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BloodDonation;
use App\Models\BloodRequest;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\NotificationRecord;
use App\Services\RedCellCompatibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BloodRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BloodRequest::query()
            ->with(['bloodType:id,code', 'component:id,code', 'facility:id,name', 'recipient:id,first_name,last_name,phone'])
            ->latest();

        if (! in_array($request->user()->role, ['admin', 'staff'], true)) {
            $recipient = $request->user()->recipient;
            abort_unless($recipient, 403);
            $query->where('recipient_id', $recipient->id);
        } elseif ($request->user()->role === 'staff') {
            $wilayaIds = $request->user()->healthcareFacilities()->select('healthcare_facilities.wilaya_id');
            $query->whereIn('wilaya_id', $wilayaIds);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'recipient', 403);
        $recipient = $request->user()->recipient;
        abort_unless($recipient, 403);

        $data = $request->validate([
            'blood_type_id' => ['required', 'integer', 'exists:blood_types,id'],
            'blood_component_id' => ['required', 'integer', 'exists:blood_components,id'],
            'wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
            'commune_id' => [
                'nullable',
                'integer',
                Rule::exists('communes', 'id')->where('wilaya_id', $request->input('wilaya_id')),
            ],
            'facility_id' => [
                'nullable',
                'integer',
                Rule::exists('healthcare_facilities', 'id')
                    ->where('verified', true)
                    ->where('accepts_requests', true)
                    ->where('wilaya_id', $request->input('wilaya_id')),
            ],
            'units' => ['required', 'integer', 'between:1,20'],
            'urgency' => ['required', Rule::in(['routine', 'urgent', 'emergency'])],
            'needed_by' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $bloodRequest = DB::transaction(function () use ($request, $recipient, $data): BloodRequest {
            $bloodRequest = $recipient->bloodRequests()->create([
                ...$data,
                'status' => 'pending_review',
            ]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'blood_request.submitted',
                'subject_type' => BloodRequest::class,
                'subject_id' => $bloodRequest->id,
                'metadata' => ['urgency' => $bloodRequest->urgency],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return $bloodRequest;
        });

        return response()->json([
            'request' => $bloodRequest->load(['bloodType:id,code', 'component:id,code']),
            'message' => 'The request is waiting for review by an authorized healthcare facility.',
        ], 201);
    }

    public function cancel(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $ownedByRequester = $request->user()->recipient?->id === $bloodRequest->recipient_id;
        abort_unless($ownedByRequester || $request->user()->role === 'admin', 404);

        DB::transaction(function () use ($request, $bloodRequest): void {
            $lockedRequest = BloodRequest::query()->lockForUpdate()->findOrFail($bloodRequest->id);
            abort_unless(in_array($lockedRequest->status, ['pending_review', 'open'], true), 409, 'This request can no longer be cancelled.');
            $lockedRequest->update(['status' => 'cancelled']);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'blood_request.cancelled',
                'subject_type' => BloodRequest::class,
                'subject_id' => $lockedRequest->id,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);
        });

        return response()->json(['request' => $bloodRequest->fresh()]);
    }

    public function review(Request $request, BloodRequest $bloodRequest): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);

        $reviewed = DB::transaction(function () use ($request, $bloodRequest, $data): BloodRequest {
            $lockedRequest = BloodRequest::query()->lockForUpdate()->findOrFail($bloodRequest->id);
            abort_unless($lockedRequest->status === 'pending_review', 409, 'Only pending requests can be reviewed.');
            $this->authorizeAssignedFacility($request, $lockedRequest);
            abort_if($lockedRequest->needed_by->isPast(), 409, 'An expired request cannot be approved.');
            $lockedRequest->update(['status' => $data['decision'] === 'approve' ? 'open' : 'rejected']);

            NotificationRecord::create([
                'user_id' => $lockedRequest->recipient()->value('user_id'),
                'type' => 'blood_request.reviewed',
                'data' => [
                    'request_id' => $lockedRequest->id,
                    'status' => $lockedRequest->status,
                ],
            ]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'blood_request.reviewed',
                'subject_type' => BloodRequest::class,
                'subject_id' => $lockedRequest->id,
                'metadata' => ['decision' => $data['decision']],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return $lockedRequest;
        });

        return response()->json(['request' => $reviewed]);
    }

    public function fulfill(Request $request, BloodRequest $bloodRequest, RedCellCompatibility $compatibility): JsonResponse
    {
        $data = $request->validate(['units' => ['required', 'integer', 'between:1,20']]);

        $updated = DB::transaction(function () use ($request, $bloodRequest, $data, $compatibility): BloodRequest {
            $lockedRequest = BloodRequest::query()->lockForUpdate()->findOrFail($bloodRequest->id);
            abort_unless(in_array($lockedRequest->status, ['open', 'partially_fulfilled'], true), 409, 'This request is not open for fulfillment.');
            $this->authorizeAssignedFacility($request, $lockedRequest);

            $remaining = $lockedRequest->units - $lockedRequest->fulfilled_units;
            abort_if($data['units'] > $remaining, 422, 'The number of units exceeds the remaining request.');

            $component = $lockedRequest->component()->firstOrFail();
            abort_unless($component->code === 'RBC', 422, 'Automatic fulfillment currently supports red-cell units only. Other component matching requires clinical validation.');

            $recipientType = $lockedRequest->bloodType()->value('code');
            $compatibleTypeIds = BloodType::query()->get(['id', 'code'])
                ->filter(fn (BloodType $bloodType): bool => $compatibility->isCompatible($bloodType->code, $recipientType))
                ->pluck('id');

            $assignedFacilityIds = $request->user()->role === 'admin'
                ? null
                : $request->user()->healthcareFacilities()->pluck('healthcare_facilities.id');

            $units = BloodDonation::query()
                ->where('status', 'available')
                ->where('blood_component_id', $lockedRequest->blood_component_id)
                ->whereIn('blood_type_id', $compatibleTypeIds)
                ->whereHas('facility', function ($query) use ($lockedRequest): void {
                    $query->where('verified', true)
                        ->where('wilaya_id', $lockedRequest->wilaya_id);

                    if ($lockedRequest->facility_id) {
                        $query->whereKey($lockedRequest->facility_id);
                    }
                })
                ->when($assignedFacilityIds !== null, fn ($query) => $query->whereIn('facility_id', $assignedFacilityIds))
                ->whereDate('expires_at', '>=', now()->toDateString())
                ->orderBy('expires_at')
                ->lockForUpdate()
                ->limit($data['units'])
                ->get();

            abort_if($units->count() < $data['units'], 409, 'There is not enough compatible, unexpired inventory in the requested area.');

            foreach ($units as $unit) {
                $unit->update(['status' => 'issued', 'blood_request_id' => $lockedRequest->id]);
            }

            $lockedRequest->fulfilled_units += $data['units'];
            $lockedRequest->status = $lockedRequest->fulfilled_units >= $lockedRequest->units ? 'fulfilled' : 'partially_fulfilled';
            $lockedRequest->save();

            NotificationRecord::create([
                'user_id' => $lockedRequest->recipient->user_id,
                'type' => 'blood_request.fulfilled',
                'data' => [
                    'request_id' => $lockedRequest->id,
                    'fulfilled_units' => $lockedRequest->fulfilled_units,
                    'status' => $lockedRequest->status,
                ],
            ]);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'blood_request.fulfilled',
                'subject_type' => BloodRequest::class,
                'subject_id' => $lockedRequest->id,
                'metadata' => ['units' => $data['units']],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return $lockedRequest;
        });

        return response()->json(['request' => $updated]);
    }

    public function matches(Request $request, BloodRequest $bloodRequest, RedCellCompatibility $compatibility): JsonResponse
    {
        $this->authorizeAssignedFacility($request, $bloodRequest);
        abort_unless($bloodRequest->status === 'open', 409, 'Only approved open requests can be matched.');
        abort_unless($bloodRequest->component()->value('code') === 'RBC', 422, 'Automated candidate filtering is limited to red-cell requests pending clinical validation.');

        $recipientType = $bloodRequest->bloodType()->value('code');
        $compatibleTypeIds = BloodType::query()->get(['id', 'code'])
            ->filter(fn (BloodType $bloodType): bool => $compatibility->isCompatible($bloodType->code, $recipientType))
            ->pluck('id');

        $donors = Donor::query()
            ->with(['bloodType:id,code', 'wilaya:id,code,name_ar,name_fr,name_en'])
            ->where('account_verified', true)
            ->where('medical_eligibility_verified', true)
            ->where('consent_to_contact', true)
            ->where('availability', 'available')
            ->where('wilaya_id', $bloodRequest->wilaya_id)
            ->whereIn('blood_type_id', $compatibleTypeIds)
            ->orderBy('id')
            ->paginate(25);

        return response()->json([
            'notice' => 'Candidate filtering is an operational aid only. A qualified clinician must verify donor eligibility and compatibility before any transfusion.',
            'data' => $donors->through(fn ($donor): array => [
                'id' => $donor->id,
                'first_name' => $donor->first_name,
                'last_name' => $donor->last_name,
                'phone' => $donor->phone,
                'blood_type' => $donor->bloodType?->code,
                'wilaya' => $donor->wilaya?->only(['code', 'name_ar', 'name_fr', 'name_en']),
            ]),
        ]);
    }

    private function authorizeAssignedFacility(Request $request, BloodRequest $bloodRequest): void
    {
        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless(
            $request->user()->healthcareFacilities()
                ->where('healthcare_facilities.wilaya_id', $bloodRequest->wilaya_id)
                ->when($bloodRequest->facility_id, fn ($query) => $query->whereKey($bloodRequest->facility_id))
                ->exists(),
            403,
            'This request is outside your assigned facility area.',
        );
    }
}
