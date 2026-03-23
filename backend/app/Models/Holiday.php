<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'business_hour_id',
        'name',
        'date',
        'recurring',
    ];

    protected $casts = [
        'date' => 'date',
        'recurring' => 'boolean',
    ];

    public function businessHour(): BelongsTo
    {
        return $this->belongsTo(BusinessHour::class);
    }
}
