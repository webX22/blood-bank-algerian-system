<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BloodComponent;
use App\Models\BloodType;
use App\Models\HealthcareFacility;
use App\Models\Wilaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'fr');
        abort_unless(in_array($locale, ['ar', 'fr', 'en'], true), 422, 'Unsupported locale.');

        return response()->json([
            'blood_types' => BloodType::query()->orderBy('code')->get(['id', 'code', 'name']),
            'components' => BloodComponent::query()->orderBy('code')->get(['id', 'code', 'name']),
            'wilayas' => Wilaya::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name_ar', 'name_fr', 'name_en']),
            'facilities' => HealthcareFacility::query()
                ->where('verified', true)
                ->where('accepts_requests', true)
                ->orderBy('name')
                ->get(['id', 'name', 'facility_type', 'wilaya_id']),
            'communes_available' => false,
            'geographic_dataset_notice' => 'Wilaya labels are a provisional navigation dataset and have not been checked against an official current registry. Commune-level data is not loaded.',
            'locale' => $locale,
        ]);
    }

    public function facilities(): JsonResponse
    {
        return response()->json([
            'data' => HealthcareFacility::query()
                ->with('wilaya:id,code,name_ar,name_fr,name_en')
                ->where('verified', true)
                ->where('accepts_requests', true)
                ->orderBy('name')
                ->paginate(30),
        ]);
    }
}
