<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CannedResponse extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'category',
        'visibility',
        'shortcut',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter responses visible to a given user.
     * A user can see: their own personal + team (if in a group) + global.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            // Global responses
            $q->where('visibility', 'global');

            // Team responses (created by any agent/admin in the same tenant)
            $q->orWhere('visibility', 'team');

            // Personal responses owned by this user
            $q->orWhere(function (Builder $q2) use ($user) {
                $q2->where('visibility', 'personal')
                   ->where('user_id', $user->id);
            });
        });
    }
}
