<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseCategory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'category_id');
    }
}
