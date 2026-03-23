<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessHour extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'timezone',
        'is_default',
        'is_24x7',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_24x7' => 'boolean',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(BusinessHourSlot::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }
}
