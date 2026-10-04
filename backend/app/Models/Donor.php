<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donor extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'phone',
        'wilaya_id',
        'commune_id',
        'blood_type_id',
        'availability',
        'account_verified',
        'medical_eligibility_verified',
        'consent_to_contact',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'account_verified' => 'boolean',
            'medical_eligibility_verified' => 'boolean',
            'consent_to_contact' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bloodType(): BelongsTo
    {
        return $this->belongsTo(BloodType::class);
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }
}
