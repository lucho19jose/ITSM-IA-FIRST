<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'priority', 'response_time', 'resolution_time', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
