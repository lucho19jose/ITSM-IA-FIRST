<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'user_id',
        'rating',
        'comment',
        'token',
        'responded_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'responded_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SatisfactionSurvey $survey) {
            if (!$survey->token) {
                $survey->token = Str::random(64);
            }
        });
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeResponded($query)
    {
        return $query->whereNotNull('responded_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('responded_at');
    }
}
