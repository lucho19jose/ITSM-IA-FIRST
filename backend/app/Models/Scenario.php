<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Scenario extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'description', 'actions', 'is_active'];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
