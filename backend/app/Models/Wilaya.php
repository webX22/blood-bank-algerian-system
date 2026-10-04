<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wilaya extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_fr', 'name_en', 'slug'];

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
