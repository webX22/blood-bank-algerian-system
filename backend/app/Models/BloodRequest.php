<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodRequest extends Model
{
    protected $fillable = [
        'recipient_id',
        'facility_id',
        'blood_type_id',
        'blood_component_id',
        'wilaya_id',
        'commune_id',
        'units',
        'urgency',
        'status',
        'needed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return ['needed_by' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(HealthcareFacility::class);
    }

    public function bloodType(): BelongsTo
    {
        return $this->belongsTo(BloodType::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(BloodComponent::class, 'blood_component_id');
    }
}
