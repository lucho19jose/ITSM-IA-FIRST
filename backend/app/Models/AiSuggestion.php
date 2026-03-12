<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestion extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'ticket_id', 'type', 'input', 'output', 'model', 'confidence',
        'was_accepted', 'tokens_used', 'processing_time_ms',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'was_accepted' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
