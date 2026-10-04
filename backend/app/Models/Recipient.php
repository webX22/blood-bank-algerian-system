<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipient extends Model
{
    protected $fillable = ['user_id', 'first_name', 'last_name', 'phone', 'wilaya_id', 'commune_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bloodRequests(): HasMany
    {
        return $this->hasMany(BloodRequest::class);
    }
}
