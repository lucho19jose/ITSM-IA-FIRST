<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCatalogItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'category', 'icon', 'form_schema',
        'is_active', 'sort_order', 'approval_required', 'estimated_days',
    ];

    protected $casts = [
        'form_schema' => 'array',
        'is_active' => 'boolean',
        'approval_required' => 'boolean',
    ];
}
