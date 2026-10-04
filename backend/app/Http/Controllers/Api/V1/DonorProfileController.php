<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Donor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DonorProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'donor' && $request->user()->donor, 403);

        $data = $request->validate([
            'consent_to_contact' => ['sometimes', 'required', 'boolean'],
            'availability' => ['sometimes', 'required', Rule::in(['available', 'temporarily_unavailable', 'unavailable'])],
        ]);

        abort_if($data === [], 422, 'Choose whether to receive contact requests or update your availability.');

        $donor = DB::transaction(function () use ($request, $data): Donor {
            $donor = Donor::query()->lockForUpdate()->findOrFail($request->user()->donor->id);
            $donor->update($data);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'donor.preferences_updated',
                'subject_type' => Donor::class,
                'subject_id' => $donor->id,
                'metadata' => ['changed_fields' => array_keys($data)],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return $donor;
        });

        return response()->json([
            'donor' => [
                'consent_to_contact' => $donor->consent_to_contact,
                'availability' => $donor->availability,
                'account_verified' => $donor->account_verified,
                'medical_eligibility_verified' => $donor->medical_eligibility_verified,
                'blood_type_id' => $donor->blood_type_id,
                'wilaya_id' => $donor->wilaya_id,
            ],
        ]);
    }
}
