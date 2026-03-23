<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'approvable_type', 'approvable_id', 'workflow_id',
        'current_step', 'status', 'requested_by',
    ];

    protected $casts = [
        'current_step' => 'integer',
    ];

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('acted_at');
    }

    public function currentStepDefinition()
    {
        return $this->workflow->steps()->where('step_order', $this->current_step)->first();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
