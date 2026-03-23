<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessHourResource;
use App\Models\BusinessHour;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusinessHourController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $items = BusinessHour::with(['slots', 'holidays'])->orderBy('name')->get();
        return BusinessHourResource::collection($items);
    }

    public function show(BusinessHour $businessHour): JsonResponse
    {
        return response()->json([
            'data' => new BusinessHourResource($businessHour->load(['slots', 'holidays'])),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'sometimes|string|max:64|timezone:all',
            'is_default' => 'sometimes|boolean',
            'is_24x7' => 'sometimes|boolean',
            'slots' => 'sometimes|array',
            'slots.*.day_of_week' => 'required_with:slots|integer|between:0,6',
            'slots.*.start_time' => 'required_with:slots|date_format:H:i',
            'slots.*.end_time' => 'required_with:slots|date_format:H:i|after:slots.*.start_time',
            'slots.*.is_working_day' => 'sometimes|boolean',
        ]);

        // If setting as default, unset other defaults for this tenant
        if (!empty($validated['is_default'])) {
            BusinessHour::where('is_default', true)->update(['is_default' => false]);
        }

        $businessHour = BusinessHour::create(collect($validated)->except('slots')->toArray());

        // Create slots inline
        if (!empty($validated['slots'])) {
            foreach ($validated['slots'] as $slotData) {
                $businessHour->slots()->create($slotData);
            }
        }

        return response()->json([
            'data' => new BusinessHourResource($businessHour->load(['slots', 'holidays'])),
        ], 201);
    }

    public function update(Request $request, BusinessHour $businessHour): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'timezone' => 'sometimes|string|max:64|timezone:all',
            'is_default' => 'sometimes|boolean',
            'is_24x7' => 'sometimes|boolean',
            'slots' => 'sometimes|array',
            'slots.*.day_of_week' => 'required_with:slots|integer|between:0,6',
            'slots.*.start_time' => 'required_with:slots|date_format:H:i',
            'slots.*.end_time' => 'required_with:slots|date_format:H:i|after:slots.*.start_time',
            'slots.*.is_working_day' => 'sometimes|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            BusinessHour::where('id', '!=', $businessHour->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $businessHour->update(collect($validated)->except('slots')->toArray());

        // Replace slots if provided (full replacement)
        if (array_key_exists('slots', $validated)) {
            $businessHour->slots()->delete();
            foreach ($validated['slots'] ?? [] as $slotData) {
                $businessHour->slots()->create($slotData);
            }
        }

        return response()->json([
            'data' => new BusinessHourResource($businessHour->load(['slots', 'holidays'])),
        ]);
    }

    public function destroy(BusinessHour $businessHour): JsonResponse
    {
        $businessHour->delete();
        return response()->json(['message' => 'Horario de atención eliminado']);
    }

    // ── Holiday CRUD (nested under business-hours) ──────────────────────

    public function holidayIndex(BusinessHour $businessHour): JsonResponse
    {
        $holidays = $businessHour->holidays()->orderBy('date')->get();

        return response()->json([
            'data' => $holidays->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'date' => $h->date->format('Y-m-d'),
                'recurring' => $h->recurring,
            ]),
        ]);
    }

    public function holidayStore(Request $request, BusinessHour $businessHour): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'recurring' => 'sometimes|boolean',
        ]);

        $holiday = $businessHour->holidays()->create($validated);

        return response()->json([
            'data' => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date->format('Y-m-d'),
                'recurring' => $holiday->recurring,
            ],
        ], 201);
    }

    public function holidayUpdate(Request $request, BusinessHour $businessHour, Holiday $holiday): JsonResponse
    {
        if ($holiday->business_hour_id !== $businessHour->id) {
            return response()->json(['message' => 'Feriado no pertenece a este horario'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'recurring' => 'sometimes|boolean',
        ]);

        $holiday->update($validated);

        return response()->json([
            'data' => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => $holiday->date->format('Y-m-d'),
                'recurring' => $holiday->recurring,
            ],
        ]);
    }

    public function holidayDestroy(BusinessHour $businessHour, Holiday $holiday): JsonResponse
    {
        if ($holiday->business_hour_id !== $businessHour->id) {
            return response()->json(['message' => 'Feriado no pertenece a este horario'], 404);
        }

        $holiday->delete();
        return response()->json(['message' => 'Feriado eliminado']);
    }
}
