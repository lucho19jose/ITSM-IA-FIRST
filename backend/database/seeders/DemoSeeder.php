<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo tenant
        $tenant = Tenant::create([
            'name' => 'Empresa Demo SAC',
            'slug' => 'empresa-demo',
            'ruc' => '20123456789',
            'plan' => 'professional',
            'is_active' => true,
        ]);

        app()->instance('tenant_id', $tenant->id);

        // Create users
        User::withoutGlobalScopes()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        User::withoutGlobalScopes()->create([
            'name' => 'Agente Soporte',
            'email' => 'agente@demo.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'tenant_id' => $tenant->id,
        ]);

        User::withoutGlobalScopes()->create([
            'name' => 'Usuario Final',
            'email' => 'usuario@demo.com',
            'password' => Hash::make('password'),
            'role' => 'end_user',
            'tenant_id' => $tenant->id,
        ]);

        // SLA Policies
        $slaPolicies = [
            ['name' => 'SLA Urgente', 'priority' => 'urgent', 'response_time' => 30, 'resolution_time' => 240],
            ['name' => 'SLA Alta', 'priority' => 'high', 'response_time' => 60, 'resolution_time' => 480],
            ['name' => 'SLA Media', 'priority' => 'medium', 'response_time' => 240, 'resolution_time' => 1440],
            ['name' => 'SLA Baja', 'priority' => 'low', 'response_time' => 480, 'resolution_time' => 2880],
        ];
        foreach ($slaPolicies as $p) {
            SlaPolicy::create($p);
        }

        // Categories
        $categories = [
            ['name' => 'Hardware', 'slug' => 'hardware', 'icon' => 'computer', 'description' => 'Problemas de hardware'],
            ['name' => 'Software', 'slug' => 'software', 'icon' => 'apps', 'description' => 'Problemas de software'],
            ['name' => 'Red', 'slug' => 'red', 'icon' => 'wifi', 'description' => 'Problemas de red y conectividad'],
            ['name' => 'Correo', 'slug' => 'correo', 'icon' => 'email', 'description' => 'Problemas de correo electrónico'],
            ['name' => 'Accesos', 'slug' => 'accesos', 'icon' => 'lock', 'description' => 'Solicitudes de acceso'],
            ['name' => 'Otros', 'slug' => 'otros', 'icon' => 'help', 'description' => 'Otros problemas'],
        ];
        foreach ($categories as $c) {
            Category::create($c);
        }

        // Departments
        $departments = [
            ['name' => 'Tecnología', 'description' => 'Departamento de TI'],
            ['name' => 'Recursos Humanos', 'description' => 'Gestión de personal'],
            ['name' => 'Finanzas', 'description' => 'Departamento financiero'],
            ['name' => 'Operaciones', 'description' => 'Operaciones generales'],
            ['name' => 'Ventas', 'description' => 'Departamento comercial'],
        ];
        foreach ($departments as $d) {
            \App\Models\Department::create($d);
        }

        // Ticket Form Fields (default configuration)
        $systemFields = [
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

        foreach ($systemFields as $field) {
            \App\Models\TicketFormField::create($field);
        }

        // KB Categories & Articles
        $kbCat = KnowledgeBaseCategory::create([
            'name' => 'Guías Generales',
            'slug' => 'guias-generales',
            'description' => 'Guías y tutoriales generales',
            'icon' => 'menu_book',
        ]);

        $adminUser = User::withoutGlobalScopes()->where('email', 'admin@demo.com')->first();

        KnowledgeBaseArticle::create([
            'category_id' => $kbCat->id,
            'title' => 'Cómo restablecer tu contraseña',
            'slug' => 'como-restablecer-contrasena',
            'content' => '<h2>Pasos para restablecer tu contraseña</h2><ol><li>Ve a la página de inicio de sesión</li><li>Haz clic en "¿Olvidaste tu contraseña?"</li><li>Ingresa tu correo electrónico</li><li>Revisa tu bandeja de entrada</li><li>Sigue el enlace para crear una nueva contraseña</li></ol>',
            'excerpt' => 'Guía paso a paso para restablecer tu contraseña de acceso al sistema.',
            'status' => 'published',
            'author_id' => $adminUser->id,
            'is_public' => true,
            'published_at' => now(),
        ]);

        KnowledgeBaseArticle::create([
            'category_id' => $kbCat->id,
            'title' => 'Cómo conectarse a la VPN corporativa',
            'slug' => 'como-conectarse-vpn',
            'content' => '<h2>Conexión VPN</h2><p>Para conectarte a la VPN corporativa necesitas:</p><ul><li>Tener instalado el cliente VPN</li><li>Credenciales de acceso proporcionadas por TI</li><li>Conexión a internet activa</li></ul><h3>Pasos</h3><ol><li>Abre el cliente VPN</li><li>Ingresa el servidor: vpn.empresa.com</li><li>Usa tus credenciales corporativas</li><li>Haz clic en Conectar</li></ol>',
            'excerpt' => 'Instrucciones para conectarte a la red corporativa mediante VPN.',
            'status' => 'published',
            'author_id' => $adminUser->id,
            'is_public' => true,
            'published_at' => now(),
        ]);
    }
}
