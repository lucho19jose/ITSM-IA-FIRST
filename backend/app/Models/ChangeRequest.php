<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const TYPE_STANDARD = 'standard';
    public const TYPE_NORMAL = 'normal';
    public const TYPE_EMERGENCY = 'emergency';

    public const TYPES = [self::TYPE_STANDARD, self::TYPE_NORMAL, self::TYPE_EMERGENCY];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ASSESSMENT = 'assessment';
    public const STATUS_CAB_REVIEW = 'cab_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IMPLEMENTING = 'implementing';
    public const STATUS_IMPLEMENTED = 'implemented';
    public const STATUS_REVIEW = 'review';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_ASSESSMENT,
        self::STATUS_CAB_REVIEW, self::STATUS_APPROVED, self::STATUS_REJECTED,
        self::STATUS_SCHEDULED, self::STATUS_IMPLEMENTING, self::STATUS_IMPLEMENTED,
        self::STATUS_REVIEW, self::STATUS_CLOSED,
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    public const RISK_LEVELS = ['low', 'medium', 'high', 'very_high'];
    public const IMPACTS = ['low', 'medium', 'high', 'extensive'];

    protected $fillable = [
        'title', 'description', 'type', 'status', 'priority', 'risk_level', 'impact',
        'category_id', 'requested_by', 'assigned_to', 'department_id',
        'reason_for_change', 'implementation_plan', 'rollback_plan', 'test_plan',
        'risk_assessment', 'scheduled_start', 'scheduled_end', 'actual_start', 'actual_end',
        'review_notes', 'cab_decision', 'cab_decided_by', 'cab_decided_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
            'cab_decided_at' => 'datetime',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cabDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cab_decided_by');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'change_request_ticket')
            ->withPivot('relationship_type', 'created_at');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ChangeRequestApproval::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED]);
    }

    public function isDeletable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isStandard(): bool
    {
        return $this->type === self::TYPE_STANDARD;
    }

    public function isEmergency(): bool
    {
        return $this->type === self::TYPE_EMERGENCY;
    }
}
