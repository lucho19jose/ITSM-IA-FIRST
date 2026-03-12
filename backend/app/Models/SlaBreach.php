<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaBreach extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'ticket_id', 'sla_policy_id', 'breach_type', 'breached_at',
    ];

    protected $casts = [
        'breached_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }
}
