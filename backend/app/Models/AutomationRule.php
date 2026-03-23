<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'execution_order',
        'stop_on_match',
        'trigger_event',
        'conditions',
        'actions',
        'last_triggered_at',
        'trigger_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stop_on_match' => 'boolean',
            'conditions' => 'array',
            'actions' => 'array',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class, 'rule_id');
    }
}
