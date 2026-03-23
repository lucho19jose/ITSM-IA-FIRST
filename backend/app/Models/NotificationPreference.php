<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'channel',
        'ticket_created',
        'ticket_assigned',
        'ticket_commented',
        'ticket_closed',
        'sla_warning',
    ];

    protected function casts(): array
    {
        return [
            'ticket_created' => 'boolean',
            'ticket_assigned' => 'boolean',
            'ticket_commented' => 'boolean',
            'ticket_closed' => 'boolean',
            'sla_warning' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create default preferences for a user in a tenant.
     */
    public static function getOrCreate(int $userId, int $tenantId): self
    {
        return self::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $userId, 'tenant_id' => $tenantId],
            [
                'channel' => 'both',
                'ticket_created' => true,
                'ticket_assigned' => true,
                'ticket_commented' => true,
                'ticket_closed' => true,
                'sla_warning' => true,
            ]
        );
    }

    /**
     * Check if email notifications are enabled for a given preference key.
     */
    public function wantsEmail(string $preferenceKey): bool
    {
        if (!in_array($this->channel, ['email', 'both'])) {
            return false;
        }

        return (bool) $this->{$preferenceKey};
    }
}
