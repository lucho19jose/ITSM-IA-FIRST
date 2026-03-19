<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'hours',
        'note',
        'executed_at',
        'billable',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'executed_at' => 'date',
            'billable' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
