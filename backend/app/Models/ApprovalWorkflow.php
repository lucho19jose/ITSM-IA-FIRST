<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('step_order');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'workflow_id');
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(ServiceCatalogItem::class, 'approval_workflow_id');
    }
}
