<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketView extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'filters',
        'columns',
        'is_default',
        'is_shared',
        'sort_order',
        'user_id',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'is_default' => 'boolean',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
