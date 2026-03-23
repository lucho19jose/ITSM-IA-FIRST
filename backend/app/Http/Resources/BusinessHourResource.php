<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessHourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'is_default' => $this->is_default,
            'is_24x7' => $this->is_24x7,
            'slots' => $this->whenLoaded('slots', function () {
                return $this->slots->map(fn ($slot) => [
                    'id' => $slot->id,
                    'day_of_week' => $slot->day_of_week,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'is_working_day' => $slot->is_working_day,
                ]);
            }),
            'holidays' => $this->whenLoaded('holidays', function () {
                return $this->holidays->map(fn ($h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => $h->date->format('Y-m-d'),
                    'recurring' => $h->recurring,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
