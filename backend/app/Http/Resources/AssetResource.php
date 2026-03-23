<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_type_id' => $this->asset_type_id,
            'name' => $this->name,
            'asset_tag' => $this->asset_tag,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'condition' => $this->condition,
            'assigned_to' => $this->assigned_to,
            'department_id' => $this->department_id,
            'location' => $this->location,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'warranty_expiry' => $this->warranty_expiry?->toDateString(),
            'vendor' => $this->vendor,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'ip_address' => $this->ip_address,
            'mac_address' => $this->mac_address,
            'custom_fields' => $this->custom_fields,
            'notes' => $this->notes,
            'asset_type' => new AssetTypeResource($this->whenLoaded('assetType')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'department' => $this->whenLoaded('department'),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
