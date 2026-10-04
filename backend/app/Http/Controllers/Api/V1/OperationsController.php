<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BloodDonation;
use App\Models\BloodRequest;
use App\Models\Donor;
use App\Models\HealthcareFacility;
use App\Models\NotificationRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $facilityIds = $request->user()->role === 'admin'
            ? null
            : $request->user()->healthcareFacilities()->pluck('healthcare_facilities.id');
        $wilayaIds = $request->user()->role === 'admin'
            ? null
            : $request->user()->healthcareFacilities()->pluck('healthcare_facilities.wilaya_id')->unique();

        abort_if($request->user()->role !== 'admin' && $facilityIds->isEmpty(), 403, 'Assign this account to a facility first.');

        $requests = BloodRequest::query();
        $facilities = HealthcareFacility::query()->where('verified', true);
        $donors = Donor::query()
            ->where('account_verified', true)
            ->where('medical_eligibility_verified', true)
            ->where('consent_to_contact', true)
            ->where('availability', 'available');
        if ($facilityIds !== null) {
            $requests->whereIn('wilaya_id', $wilayaIds);
            $facilities->whereIn('id', $facilityIds);
            $donors->whereIn('wilaya_id', $wilayaIds);
        }

        return response()->json([
            'requests_by_status' => (clone $requests)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'verified_facilities' => $facilities->count(),
            'available_units' => BloodDonation::query()
                ->when($facilityIds !== null, fn ($query) => $query->whereIn('facility_id', $facilityIds))
                ->where('status', 'available')
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', now()->toDateString())
                ->count(),
            'eligible_donor_profiles' => $donors->count(),
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $facilityIds = $request->user()->role === 'admin'
            ? null
            : $request->user()->healthcareFacilities()->pluck('healthcare_facilities.id');
        abort_if($request->user()->role !== 'admin' && $facilityIds->isEmpty(), 403, 'Assign this account to a facility first.');

        $inventory = BloodDonation::query()
            ->join('healthcare_facilities', 'blood_donations.facility_id', '=', 'healthcare_facilities.id')
            ->join('blood_types', 'blood_donations.blood_type_id', '=', 'blood_types.id')
            ->join('blood_components', 'blood_donations.blood_component_id', '=', 'blood_components.id')
            ->where('healthcare_facilities.verified', true)
            ->when($facilityIds !== null, fn ($query) => $query->whereIn('blood_donations.facility_id', $facilityIds))
            ->where('blood_donations.status', 'available')
            ->whereNotNull('blood_donations.expires_at')
            ->whereDate('blood_donations.expires_at', '>=', now()->toDateString())
            ->select([
                'healthcare_facilities.id as facility_id',
                'healthcare_facilities.name as facility_name',
                'healthcare_facilities.wilaya_id',
                'blood_types.code as blood_type',
                'blood_components.code as component',
                DB::raw('count(blood_donations.id) as units'),
            ])
            ->groupBy('healthcare_facilities.id', 'healthcare_facilities.name', 'healthcare_facilities.wilaya_id', 'blood_types.code', 'blood_components.code')
            ->orderBy('healthcare_facilities.name')
            ->paginate(50);

        return response()->json($inventory);
    }

    public function storeFacility(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:50', 'unique:healthcare_facilities,code'],
            'facility_type' => ['required', Rule::in(['hospital', 'blood_bank', 'clinic', 'other'])],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
            'commune_id' => [
                'nullable',
                'integer',
                Rule::exists('communes', 'id')->where('wilaya_id', $request->input('wilaya_id')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $facility = DB::transaction(function () use ($request, $data): HealthcareFacility {
            $facility = HealthcareFacility::create([...$data, 'created_by' => $request->user()->id]);
            $this->audit($request, 'facility.created', $facility);

            return $facility;
        });

        return response()->json(['facility' => $facility], 201);
    }

    public function verifyFacility(Request $request, HealthcareFacility $facility): JsonResponse
    {
        abort_if($request->user()->id === $facility->created_by, 403, 'Facility verification must be performed by a different administrator.');

        $data = $request->validate([
            'verified' => ['required', 'boolean'],
            'accepts_requests' => ['required', 'boolean'],
        ]);

        abort_if($data['accepts_requests'] && ! $data['verified'], 422, 'A facility must be verified before accepting requests.');
        abort_if($facility->verified && ! $data['verified'] && $facility->staff()->exists(), 409, 'Reassign staff before revoking facility verification.');

        DB::transaction(function () use ($request, $facility, $data): void {
            $facility->update($data);
            $this->audit($request, 'facility.verification_updated', $facility);
        });

        return response()->json(['facility' => $facility->fresh()]);
    }

    public function assignStaff(Request $request, HealthcareFacility $facility, User $user): JsonResponse
    {
        abort_unless($facility->verified, 409, 'Staff can only be assigned to a verified facility.');
        abort_unless($user->role === 'staff', 422, 'Only provisioned staff accounts may be assigned.');

        DB::transaction(function () use ($request, $facility, $user): void {
            $facility->staff()->syncWithoutDetaching([
                $user->id => ['assigned_by' => $request->user()->id],
            ]);
            $this->audit($request, 'facility.staff_assigned', $facility, ['staff_user_id' => $user->id]);
        });

        return response()->json(['staff_user_id' => $user->id, 'facility_id' => $facility->id], 201);
    }

    public function unassignStaff(Request $request, HealthcareFacility $facility, User $user): JsonResponse
    {
        DB::transaction(function () use ($request, $facility, $user): void {
            if ($facility->staff()->whereKey($user->id)->exists()) {
                $facility->staff()->detach($user->id);
                $this->audit($request, 'facility.staff_unassigned', $facility, ['staff_user_id' => $user->id]);
            }
        });

        return response()->json(['message' => 'Staff assignment removed.']);
    }

    public function storeDonation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'donor_id' => ['required', 'integer', 'exists:donors,id'],
            'facility_id' => [
                'required',
                'integer',
                Rule::exists('healthcare_facilities', 'id')->where('verified', true),
            ],
            'blood_type_id' => ['required', 'integer', 'exists:blood_types,id'],
            'blood_component_id' => ['required', 'integer', 'exists:blood_components,id'],
            'bag_code' => ['nullable', 'string', 'max:80', 'unique:blood_donations,bag_code'],
            'volume_ml' => ['nullable', 'integer', 'between:1,5000'],
            'donated_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        abort_unless(
            $request->user()->role === 'admin'
            || $request->user()->healthcareFacilities()
                ->whereKey($data['facility_id'])
                ->where('healthcare_facilities.verified', true)
                ->exists(),
            403,
            'Donation entry is restricted to the assigned verified facility.',
        );

        $donation = DB::transaction(function () use ($request, $data): BloodDonation {
            $donor = Donor::query()->lockForUpdate()->findOrFail($data['donor_id']);
            abort_unless($donor->account_verified, 422, 'The donor account must be verified before recording a collection.');

            $donation = BloodDonation::create([
                ...$data,
                'status' => 'collected',
                'recorded_by' => $request->user()->id,
            ]);

            $this->audit($request, 'donation.recorded', $donation);

            return $donation;
        });

        return response()->json(['donation' => $donation], 201);
    }

    public function updateDonorVerification(Request $request, Donor $donor): JsonResponse
    {
        abort_unless(
            $request->user()->role === 'admin'
            || $request->user()->healthcareFacilities()->where('healthcare_facilities.wilaya_id', $donor->wilaya_id)->exists(),
            404,
        );

        $data = $request->validate([
            'account_verified' => ['sometimes', 'boolean'],
            'medical_eligibility_verified' => ['sometimes', 'boolean'],
            'availability' => ['sometimes', Rule::in(['available', 'temporarily_unavailable', 'unavailable'])],
            'blood_type_id' => ['sometimes', 'nullable', 'integer', 'exists:blood_types,id'],
        ]);

        abort_if($data === [], 422, 'At least one review field is required.');

        DB::transaction(function () use ($request, $donor, $data): void {
            $lockedDonor = Donor::query()->lockForUpdate()->findOrFail($donor->id);
            $lockedDonor->update($data);
            $this->audit($request, 'donor.verification_updated', $lockedDonor, [
                'changed_fields' => array_keys($data),
            ]);
        });

        return response()->json(['donor' => $donor->fresh()]);
    }

    public function donors(Request $request): JsonResponse
    {
        $query = Donor::query()
            ->with(['bloodType:id,code', 'wilaya:id,code,name_ar,name_fr,name_en'])
            ->when(
                $request->user()->role !== 'admin',
                fn ($query) => $query->whereIn(
                    'wilaya_id',
                    $request->user()->healthcareFacilities()->select('healthcare_facilities.wilaya_id'),
                ),
            )
            ->orderBy('id');

        return response()->json($query->paginate(30)->through(fn (Donor $donor): array => [
            'id' => $donor->id,
            'first_name' => $donor->first_name,
            'last_name' => $donor->last_name,
            'blood_type' => $donor->bloodType?->code,
            'wilaya' => $donor->wilaya?->only(['code', 'name_ar', 'name_fr', 'name_en']),
            'availability' => $donor->availability,
            'account_verified' => $donor->account_verified,
            'medical_eligibility_verified' => $donor->medical_eligibility_verified,
            'consent_to_contact' => $donor->consent_to_contact,
        ]));
    }

    public function updateDonationStatus(Request $request, BloodDonation $donation): JsonResponse
    {
        abort_unless(
            $request->user()->role === 'admin'
            || $request->user()->healthcareFacilities()
                ->whereKey($donation->facility_id)
                ->exists(),
            404,
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(['screening', 'available', 'discarded'])],
            'expires_at' => ['required_if:status,available', 'nullable', 'date', 'after_or_equal:today'],
        ]);

        $updated = DB::transaction(function () use ($request, $donation, $data): BloodDonation {
            $lockedDonation = BloodDonation::query()->lockForUpdate()->findOrFail($donation->id);
            $allowed = [
                'collected' => ['screening', 'discarded'],
                'screening' => ['available', 'discarded'],
                'available' => ['discarded'],
            ];
            abort_unless(in_array($data['status'], $allowed[$lockedDonation->status] ?? [], true), 409, 'This donation status transition is not allowed.');

            if ($data['status'] === 'available') {
                abort_unless(
                    HealthcareFacility::query()->whereKey($lockedDonation->facility_id)->where('verified', true)->exists(),
                    422,
                    'Donation inventory can only be released at a verified facility.',
                );
                $lockedDonation->expires_at = $data['expires_at'];
            }

            $lockedDonation->status = $data['status'];
            $lockedDonation->save();
            $this->audit($request, 'donation.status_updated', $lockedDonation, ['status' => $data['status']]);

            return $lockedDonation;
        });

        return response()->json(['donation' => $updated]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403, 'Audit logs require administrator access.');

        return response()->json(AuditLog::query()->with('user:id,name,role')->latest('created_at')->paginate(50));
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(
            NotificationRecord::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(30),
        );
    }

    public function markNotificationRead(Request $request, NotificationRecord $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => now()]);

        return response()->json(['notification' => $notification]);
    }

    private function audit(Request $request, string $action, object $subject, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }
}
