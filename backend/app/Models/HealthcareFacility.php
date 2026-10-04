<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HealthcareFacility extends Model
{
    protected $fillable = [
        'name', 'created_by', 'code', 'facility_type', 'phone', 'email', 'wilaya_id', 'commune_id',
        'address', 'verified', 'accepts_requests',
    ];

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'accepts_requests' => 'boolean'];
    }

    public function wilaya(): BelongsTo
    {
        return $this->belongsTo(Wilaya::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_staff')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
