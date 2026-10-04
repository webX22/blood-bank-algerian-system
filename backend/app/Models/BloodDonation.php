<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodDonation extends Model
{
    protected $fillable = [
        'donor_id', 'facility_id', 'blood_type_id', 'blood_component_id', 'blood_request_id', 'bag_code',
        'volume_ml', 'donated_at', 'expires_at', 'status', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['donated_at' => 'datetime', 'expires_at' => 'date'];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(HealthcareFacility::class);
    }
}
