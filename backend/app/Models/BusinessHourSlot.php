<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHourSlot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'business_hour_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_working_day',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_working_day' => 'boolean',
    ];

    public function businessHour(): BelongsTo
    {
        return $this->belongsTo(BusinessHour::class);
    }
}
