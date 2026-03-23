<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Problem extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_LOGGED = 'logged';
    public const STATUS_CATEGORIZED = 'categorized';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_ROOT_CAUSE_IDENTIFIED = 'root_cause_identified';
    public const STATUS_KNOWN_ERROR = 'known_error';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_LOGGED,
        self::STATUS_CATEGORIZED,
        self::STATUS_INVESTIGATING,
        self::STATUS_ROOT_CAUSE_IDENTIFIED,
        self::STATUS_KNOWN_ERROR,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    public const IMPACTS = ['low', 'medium', 'high', 'extensive'];
    public const URGENCIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'title', 'description', 'status', 'priority', 'impact', 'urgency',
        'category_id', 'assigned_to', 'department_id',
        'root_cause', 'workaround', 'resolution',
        'is_known_error', 'known_error_id', 'related_incidents_count',
        'detected_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_known_error' => 'boolean',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'problem_ticket')->withPivot('created_at');
    }

    public function knownErrors(): HasMany
    {
        return $this->hasMany(KnownError::class);
    }

    /**
     * Recalculate the related_incidents_count from the pivot table.
     */
    public function refreshIncidentsCount(): void
    {
        $this->update(['related_incidents_count' => $this->tickets()->count()]);
    }
}
