<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'report_type',
        'config',
        'is_shared',
        'schedule_cron',
        'schedule_emails',
        'last_run_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_shared' => 'boolean',
            'schedule_emails' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
