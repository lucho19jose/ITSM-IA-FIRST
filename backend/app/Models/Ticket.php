<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const SOURCE_PORTAL = 'portal';
    public const SOURCE_EMAIL = 'email';
    public const SOURCE_CHATBOT = 'chatbot';
    public const SOURCE_CATALOG = 'catalog';
    public const SOURCE_API = 'api';
    public const SOURCE_PHONE = 'phone';

    public const SOURCES = [
        self::SOURCE_PORTAL,
        self::SOURCE_EMAIL,
        self::SOURCE_CHATBOT,
        self::SOURCE_CATALOG,
        self::SOURCE_API,
        self::SOURCE_PHONE,
    ];

    protected $fillable = [
        'ticket_number', 'title', 'description', 'type', 'status', 'status_details',
        'priority', 'impact', 'urgency', 'source', 'category_id', 'department_id',
        'subcategory', 'item', 'requester_id', 'assigned_to', 'sla_policy_id',
        'responded_at', 'resolved_at', 'closed_at', 'due_date',
        'planned_start_date', 'planned_end_date', 'planned_effort',
        'response_due_at', 'resolution_due_at', 'tags', 'custom_fields',
        'satisfaction_rating', 'satisfaction_comment', 'resolution_notes',
        'approval_status', 'association_type', 'major_incident_type',
        'contact_number', 'requester_location', 'specific_subject',
        'customers_impacted', 'impacted_locations',
        'agent_group_id', 'is_spam', 'asset_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'custom_fields' => 'array',
            'impacted_locations' => 'array',
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'due_date' => 'datetime',
            'planned_start_date' => 'datetime',
            'planned_end_date' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'is_spam' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = 'TK-' . now()->format('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function associations(): HasMany
    {
        return $this->hasMany(TicketAssociation::class);
    }

    public function agentGroup(): BelongsTo
    {
        return $this->belongsTo(AgentGroup::class);
    }

    public function satisfactionSurvey(): HasOne
    {
        return $this->hasOne(SatisfactionSurvey::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_favorites')->withPivot('created_at');
    }

    public function problems(): BelongsToMany
    {
        return $this->belongsToMany(Problem::class, 'problem_ticket')->withPivot('created_at');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_ticket');
    }

    /**
     * Compute lifecycle state tag (Freshservice-style).
     */
    public function getLifecycleStateAttribute(): ?string
    {
        if (in_array($this->status, ['resolved', 'closed'])) {
            return null;
        }

        if ($this->resolution_due_at && $this->resolution_due_at->isPast()) {
            return 'overdue';
        }

        if (!$this->responded_at && $this->status === 'open') {
            return 'new';
        }

        $lastCommentUserId = $this->attributes['last_comment_user_id'] ?? null;
        $lastCommentInternal = $this->attributes['last_comment_internal'] ?? null;

        if ($lastCommentUserId === null) {
            $lastComment = $this->comments()->latest()->first(['user_id', 'is_internal']);
            $lastCommentUserId = $lastComment?->user_id;
            $lastCommentInternal = $lastComment?->is_internal;
        }

        if ($lastCommentUserId && (int) $lastCommentUserId === $this->requester_id && !$lastCommentInternal) {
            return 'requester_replied';
        }

        return null;
    }

    /**
     * Scope to add last comment info as subquery (avoids N+1 on lists).
     */
    public function scopeWithLastComment($query)
    {
        $query->addSelect([
            'last_comment_user_id' => TicketComment::select('user_id')
                ->whereColumn('ticket_id', 'tickets.id')
                ->latest()
                ->limit(1),
            'last_comment_internal' => TicketComment::select('is_internal')
                ->whereColumn('ticket_id', 'tickets.id')
                ->latest()
                ->limit(1),
        ]);
    }
}
