<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ServiceCatalogItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'category', 'icon', 'form_schema',
        'is_active', 'sort_order', 'approval_required', 'estimated_days',
        'requires_approval', 'approval_workflow_id',
    ];

    protected $casts = [
        'form_schema' => 'array',
        'is_active' => 'boolean',
        'approval_required' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    public function approvalWorkflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
