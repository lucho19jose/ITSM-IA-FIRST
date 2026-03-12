<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TicketFormField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketFormFieldController extends Controller
{
    /**
     * Return all form fields for the tenant, ordered by section then sort_order.
     * Filters by role_visibility based on the authenticated user's role.
     */
    public function index(Request $request): JsonResponse
    {
        $userRole = $request->user()->role;

        $fields = TicketFormField::orderBy('section')
            ->orderBy('sort_order')
            ->get()
            ->filter(function (TicketFormField $field) use ($userRole) {
                // If role_visibility is null, all roles can see the field
                if (is_null($field->role_visibility)) {
                    return true;
                }

                return in_array($userRole, $field->role_visibility);
            })
            ->values();

        return response()->json(['data' => $fields]);
    }

    /**
     * Bulk update form fields (admin only).
     * System fields cannot change field_key or field_type.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*.id' => 'required|integer|exists:ticket_form_fields,id',
            'fields.*.is_visible' => 'sometimes|boolean',
            'fields.*.is_required' => 'sometimes|boolean',
            'fields.*.sort_order' => 'sometimes|integer|min:0',
            'fields.*.label' => 'sometimes|string|max:100',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.help_text' => 'nullable|string|max:255',
            'fields.*.default_value' => 'nullable|string|max:255',
            'fields.*.role_visibility' => 'nullable|array',
            'fields.*.role_visibility.*' => 'string|in:admin,agent,end_user',
        ]);

        $updated = [];

        foreach ($validated['fields'] as $fieldData) {
            $field = TicketFormField::find($fieldData['id']);

            if (!$field) {
                continue;
            }

            $allowedKeys = [
                'is_visible', 'is_required', 'sort_order', 'label',
                'placeholder', 'help_text', 'default_value', 'role_visibility',
            ];

            $updateData = array_intersect_key($fieldData, array_flip($allowedKeys));

            $field->update($updateData);
            $updated[] = $field->fresh();
        }

        return response()->json([
            'message' => count($updated) . ' campo(s) actualizado(s)',
            'data' => $updated,
        ]);
    }

    /**
     * Create a new custom field (admin only).
     */
    public function storeCustom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_key' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = TicketFormField::where('field_key', $value)->exists();
                    if ($exists) {
                        $fail('El campo "' . $value . '" ya existe para este tenant.');
                    }
                },
            ],
            'label' => 'required|string|max:100',
            'field_type' => 'required|string|in:text,textarea,rich_text,select,number,date,checkbox,email,phone,url,tags,file',
            'is_visible' => 'sometimes|boolean',
            'is_required' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'options' => 'nullable|array',
            'options.*.label' => 'required_with:options|string',
            'options.*.value' => 'required_with:options|string',
            'default_value' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'section' => 'sometimes|string|in:main,details',
            'help_text' => 'nullable|string|max:255',
            'role_visibility' => 'nullable|array',
            'role_visibility.*' => 'string|in:admin,agent,end_user',
        ]);

        $validated['is_system'] = false;

        $field = TicketFormField::create($validated);

        return response()->json([
            'message' => 'Campo personalizado creado',
            'data' => $field,
        ], 201);
    }

    /**
     * Delete a custom (non-system) field (admin only).
     */
    public function destroyCustom(TicketFormField $field): JsonResponse
    {
        if ($field->is_system) {
            return response()->json([
                'message' => 'No se puede eliminar un campo del sistema',
            ], 422);
        }

        $field->delete();

        return response()->json([
            'message' => 'Campo personalizado eliminado',
        ]);
    }
}
