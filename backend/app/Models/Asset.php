<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'asset_type_id', 'name', 'asset_tag', 'serial_number',
        'status', 'condition', 'assigned_to', 'department_id',
        'location', 'purchase_date', 'purchase_cost', 'warranty_expiry',
        'vendor', 'manufacturer', 'model', 'ip_address', 'mac_address',
        'custom_fields', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'asset_ticket');
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(AssetRelationship::class, 'source_asset_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(AssetRelationship::class, 'target_asset_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }
}
