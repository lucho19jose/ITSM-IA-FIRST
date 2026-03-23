<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id', 'step_order', 'approver_type', 'approver_id',
        'approver_role', 'auto_approve_after_hours',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'approver_id' => 'integer',
        'auto_approve_after_hours' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
