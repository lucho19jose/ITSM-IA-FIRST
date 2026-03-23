<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaPolicy extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'priority', 'response_time', 'resolution_time', 'is_active', 'business_hour_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function businessHour(): BelongsTo
    {
        return $this->belongsTo(BusinessHour::class);
    }
}
