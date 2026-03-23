<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'custom_domain', 'logo_path', 'favicon_path',
        'ruc', 'plan', 'plan_limits', 'plan_expires_at',
        'max_agents', 'max_tickets_per_month', 'max_storage_mb',
        'features', 'settings', 'is_active', 'trial_ends_at',
        'suspended_at', 'suspension_reason',
    ];

    protected $casts = [
        'settings' => 'array',
        'plan_limits' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'plan_expires_at' => 'datetime',
        'suspended_at' => 'datetime',
        'max_agents' => 'integer',
        'max_tickets_per_month' => 'integer',
        'max_storage_mb' => 'integer',
    ];

    protected $appends = ['logo_url', 'favicon_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon_path
            ? asset('storage/' . $this->favicon_path)
            : null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
