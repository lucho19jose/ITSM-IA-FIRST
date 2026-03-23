<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'provider',
        'name',
        'config',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }
}
