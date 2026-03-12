<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TicketFormField;
use Illuminate\Database\Seeder;

class TicketFormFieldSeeder extends Seeder
{
    /**
     * Seed default ticket form fields for tenants.
     *
     * Usage:
     *   php artisan db:seed --class=TicketFormFieldSeeder                  # All tenants
     *   php artisan db:seed --class=TicketFormFieldSeeder -- --tenant=1    # Specific tenant
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }
    }

    private function seedForTenant(Tenant $tenant): void
    {
        // Set tenant context so BelongsToTenant trait auto-fills tenant_id
        app()->instance('tenant_id', $tenant->id);

        // Check if fields already exist for this tenant
        $existingCount = TicketFormField::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        if ($existingCount > 0) {
            $this->command?->info("Tenant '{$tenant->name}' (ID: {$tenant->id}) already has {$existingCount} form fields — skipping.");
            return;
        }

        $systemFields = $this->getSystemFields();

        foreach ($systemFields as $field) {
            TicketFormField::create($field);
        }

        $this->command?->info("Tenant '{$tenant->name}' (ID: {$tenant->id}) — " . count($systemFields) . ' form fields created.');
    }

    public static function getSystemFields(): array
    {
        return [
            ['field_key' => 'title', 'label' => 'Asunto', 'field_type' => 'text', 'is_visible' => true, 'is_required' => true, 'is_system' => true, 'sort_order' => 1, 'section' => 'main', 'placeholder' => 'Resumen breve del problema'],
            ['field_key' => 'description', 'label' => 'Descripción', 'field_type' => 'rich_text', 'is_visible' => true, 'is_required' => true, 'is_system' => true, 'sort_order' => 2, 'section' => 'main', 'placeholder' => 'Describe tu problema o solicitud en detalle...'],
            ['field_key' => 'type', 'label' => 'Tipo', 'field_type' => 'select', 'is_visible' => true, 'is_required' => true, 'is_system' => true, 'sort_order' => 1, 'section' => 'details', 'options' => [['label' => 'Incidente', 'value' => 'incident'], ['label' => 'Solicitud', 'value' => 'request'], ['label' => 'Problema', 'value' => 'problem'], ['label' => 'Cambio', 'value' => 'change']], 'default_value' => 'incident'],
            ['field_key' => 'priority', 'label' => 'Prioridad', 'field_type' => 'select', 'is_visible' => true, 'is_required' => true, 'is_system' => true, 'sort_order' => 2, 'section' => 'details', 'options' => [['label' => 'Baja', 'value' => 'low'], ['label' => 'Media', 'value' => 'medium'], ['label' => 'Alta', 'value' => 'high'], ['label' => 'Urgente', 'value' => 'urgent']], 'default_value' => 'medium'],
            ['field_key' => 'category_id', 'label' => 'Categoría', 'field_type' => 'select', 'is_visible' => true, 'is_required' => false, 'is_system' => true, 'sort_order' => 3, 'section' => 'details', 'placeholder' => 'Selecciona una categoría'],
            ['field_key' => 'source', 'label' => 'Origen', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 4, 'section' => 'details', 'options' => [['label' => 'Portal', 'value' => 'portal'], ['label' => 'Email', 'value' => 'email'], ['label' => 'Teléfono', 'value' => 'phone'], ['label' => 'Chat', 'value' => 'chatbot']], 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'assigned_to', 'label' => 'Asignar a', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 5, 'section' => 'details', 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'due_date', 'label' => 'Fecha límite', 'field_type' => 'date', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 6, 'section' => 'details'],
            ['field_key' => 'tags', 'label' => 'Etiquetas', 'field_type' => 'tags', 'is_visible' => true, 'is_required' => false, 'is_system' => true, 'sort_order' => 3, 'section' => 'main'],
            ['field_key' => 'attachments', 'label' => 'Archivos adjuntos', 'field_type' => 'file', 'is_visible' => true, 'is_required' => false, 'is_system' => true, 'sort_order' => 4, 'section' => 'main'],
            ['field_key' => 'cc_emails', 'label' => 'CC', 'field_type' => 'email', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 5, 'section' => 'main', 'placeholder' => 'Agregar correos en copia'],
            ['field_key' => 'department_id', 'label' => 'Departamento', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 7, 'section' => 'details', 'placeholder' => 'Selecciona un departamento'],
            ['field_key' => 'subcategory', 'label' => 'Subcategoría', 'field_type' => 'text', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 8, 'section' => 'details', 'placeholder' => 'Subcategoría del ticket'],
            ['field_key' => 'item', 'label' => 'Elemento', 'field_type' => 'text', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 9, 'section' => 'details', 'placeholder' => 'Elemento específico'],
            ['field_key' => 'impact', 'label' => 'Impacto', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 10, 'section' => 'details', 'options' => [['label' => 'Bajo', 'value' => 'low'], ['label' => 'Medio', 'value' => 'medium'], ['label' => 'Alto', 'value' => 'high']], 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'urgency', 'label' => 'Urgencia', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 11, 'section' => 'details', 'options' => [['label' => 'Baja', 'value' => 'low'], ['label' => 'Media', 'value' => 'medium'], ['label' => 'Alta', 'value' => 'high']], 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'approval_status', 'label' => 'Estado de aprobación', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 12, 'section' => 'details', 'options' => [['label' => 'No solicitado', 'value' => 'not_requested'], ['label' => 'Solicitado', 'value' => 'requested'], ['label' => 'Aprobado', 'value' => 'approved'], ['label' => 'Rechazado', 'value' => 'rejected']], 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'planned_start_date', 'label' => 'Fecha de inicio planificada', 'field_type' => 'date', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 13, 'section' => 'details', 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'planned_end_date', 'label' => 'Fecha de finalización planificada', 'field_type' => 'date', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 14, 'section' => 'details', 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'planned_effort', 'label' => 'Esfuerzo planificado', 'field_type' => 'text', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 15, 'section' => 'details', 'placeholder' => 'Ej: 8h, 2d', 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'association_type', 'label' => 'Tipo de asociación', 'field_type' => 'select', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 16, 'section' => 'details', 'options' => [['label' => 'Padre', 'value' => 'parent'], ['label' => 'Hijo', 'value' => 'child'], ['label' => 'Relacionado', 'value' => 'related'], ['label' => 'Causa', 'value' => 'cause']], 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'major_incident_type', 'label' => 'Tipo de incidente mayor', 'field_type' => 'text', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 17, 'section' => 'details', 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'contact_number', 'label' => 'Número de contacto', 'field_type' => 'phone', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 6, 'section' => 'main', 'placeholder' => 'Número de teléfono'],
            ['field_key' => 'requester_location', 'label' => 'Ubicación del solicitante', 'field_type' => 'text', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 7, 'section' => 'main', 'placeholder' => 'Oficina, piso, ubicación'],
            ['field_key' => 'specific_subject', 'label' => 'Asunto específico', 'field_type' => 'text', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 8, 'section' => 'main', 'placeholder' => 'Detalle específico del asunto'],
            ['field_key' => 'customers_impacted', 'label' => 'Clientes impactados', 'field_type' => 'number', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 18, 'section' => 'details', 'placeholder' => '0', 'role_visibility' => ['admin', 'agent']],
            ['field_key' => 'impacted_locations', 'label' => 'Ubicaciones impactadas', 'field_type' => 'tags', 'is_visible' => false, 'is_required' => false, 'is_system' => true, 'sort_order' => 19, 'section' => 'details', 'role_visibility' => ['admin', 'agent']],
        ];
    }
}
