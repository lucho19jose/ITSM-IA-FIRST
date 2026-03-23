<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRelationship extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'source_asset_id', 'target_asset_id', 'relationship_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'source_asset_id');
    }

    public function targetAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'target_asset_id');
    }
}
