<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\Holiday;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AssetService;
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
        // Business Hours (default: Mon-Fri 09:00-18:00, Lima TZ)
        $businessHour = BusinessHour::create([
            'name' => 'Horario Oficina',
            'timezone' => 'America/Lima',
            'is_default' => true,
            'is_24x7' => false,
        ]);

        // Monday (1) through Friday (5), 09:00 - 18:00
        for ($day = 1; $day <= 5; $day++) {
            $businessHour->slots()->create([
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'is_working_day' => true,
            ]);
        }
        // Saturday and Sunday - non-working
        foreach ([0, 6] as $day) {
            $businessHour->slots()->create([
                'day_of_week' => $day,
                'start_time' => '00:00',
                'end_time' => '00:00',
                'is_working_day' => false,
            ]);
        }

        // 24x7 schedule for urgent SLAs
        $bh24x7 = BusinessHour::create([
            'name' => 'Soporte 24/7',
            'timezone' => 'America/Lima',
            'is_default' => false,
            'is_24x7' => true,
        ]);

        // Peruvian national holidays (recurring)
        $peruvianHolidays = [
            ['name' => 'Año Nuevo', 'date' => '2026-01-01', 'recurring' => true],
            ['name' => 'Jueves Santo', 'date' => '2026-04-02', 'recurring' => false],
            ['name' => 'Viernes Santo', 'date' => '2026-04-03', 'recurring' => false],
            ['name' => 'Día del Trabajo', 'date' => '2026-05-01', 'recurring' => true],
            ['name' => 'San Pedro y San Pablo', 'date' => '2026-06-29', 'recurring' => true],
            ['name' => 'Día de la Independencia', 'date' => '2026-07-28', 'recurring' => true],
            ['name' => 'Fiestas Patrias', 'date' => '2026-07-29', 'recurring' => true],
            ['name' => 'Batalla de Junín', 'date' => '2026-08-06', 'recurring' => true],
            ['name' => 'Santa Rosa de Lima', 'date' => '2026-08-30', 'recurring' => true],
            ['name' => 'Combate de Angamos', 'date' => '2026-10-08', 'recurring' => true],
            ['name' => 'Todos los Santos', 'date' => '2026-11-01', 'recurring' => true],
            ['name' => 'Inmaculada Concepción', 'date' => '2026-12-08', 'recurring' => true],
            ['name' => 'Batalla de Ayacucho', 'date' => '2026-12-09', 'recurring' => true],
            ['name' => 'Navidad', 'date' => '2026-12-25', 'recurring' => true],
        ];

        foreach ($peruvianHolidays as $holiday) {
            Holiday::create([
                'business_hour_id' => null, // Tenant-wide holidays
                ...$holiday,
            ]);
        }

        // SLA Policies (link urgent to 24x7, others to office hours)
        foreach ($slaPolicies as $p) {
            $p['business_hour_id'] = $p['priority'] === 'urgent' ? $bh24x7->id : $businessHour->id;
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

        // Canned Responses
        $agentUser = User::withoutGlobalScopes()->where('email', 'agente@demo.com')->first();

        \App\Models\CannedResponse::create([
            'user_id' => $adminUser->id,
            'title' => 'Saludo inicial',
            'content' => '<p>Hola, gracias por contactarnos. Mi nombre es {{agent_name}} y estaré atendiendo su solicitud. ¿En qué puedo ayudarle?</p>',
            'category' => 'General',
            'visibility' => 'global',
            'shortcut' => '/saludo',
        ]);

        \App\Models\CannedResponse::create([
            'user_id' => $adminUser->id,
            'title' => 'Solicitud de más información',
            'content' => '<p>Para poder ayudarle mejor, necesitamos la siguiente información adicional:</p><ul><li>Descripción detallada del problema</li><li>Capturas de pantalla (si aplica)</li><li>Equipo o aplicación afectada</li></ul><p>Quedamos atentos a su respuesta.</p>',
            'category' => 'General',
            'visibility' => 'global',
            'shortcut' => '/masinfo',
        ]);

        \App\Models\CannedResponse::create([
            'user_id' => $adminUser->id,
            'title' => 'Ticket resuelto - Cierre',
            'content' => '<p>Hemos resuelto su solicitud. Si el problema persiste o tiene alguna consulta adicional, no dude en reabrir este ticket o crear uno nuevo.</p><p>¡Gracias por su paciencia!</p>',
            'category' => 'Cierre',
            'visibility' => 'global',
            'shortcut' => '/resuelto',
        ]);

        \App\Models\CannedResponse::create([
            'user_id' => $agentUser->id,
            'title' => 'Reiniciar contraseña',
            'content' => '<p>Su contraseña ha sido restablecida exitosamente. Las nuevas credenciales han sido enviadas a su correo electrónico registrado.</p><p>Por favor, cambie su contraseña temporal en el primer inicio de sesión.</p>',
            'category' => 'Accesos',
            'visibility' => 'team',
            'shortcut' => '/resetpwd',
        ]);

        \App\Models\CannedResponse::create([
            'user_id' => $agentUser->id,
            'title' => 'Escalamiento a nivel 2',
            'content' => '<p>Este ticket ha sido escalado al equipo de soporte nivel 2 para su revisión. Un especialista se pondrá en contacto con usted en breve.</p><p>Tiempo estimado de respuesta: 4 horas hábiles.</p>',
            'category' => 'Escalamiento',
            'visibility' => 'team',
            'shortcut' => '/escalar',
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

        // ─── Asset Types & Assets (CMDB) ─────────────────────────────────────
        $assetService = new AssetService();

        $laptopType = AssetType::create([
            'name' => 'Laptop',
            'icon' => 'laptop',
            'fields' => [
                ['name' => 'ram', 'label' => 'RAM', 'type' => 'text'],
                ['name' => 'cpu', 'label' => 'CPU', 'type' => 'text'],
                ['name' => 'storage', 'label' => 'Almacenamiento', 'type' => 'text'],
                ['name' => 'os', 'label' => 'Sistema Operativo', 'type' => 'text'],
            ],
        ]);

        $serverType = AssetType::create([
            'name' => 'Servidor',
            'icon' => 'dns',
            'fields' => [
                ['name' => 'ram', 'label' => 'RAM', 'type' => 'text'],
                ['name' => 'cpu', 'label' => 'CPU', 'type' => 'text'],
                ['name' => 'storage', 'label' => 'Almacenamiento', 'type' => 'text'],
                ['name' => 'os', 'label' => 'Sistema Operativo', 'type' => 'text'],
                ['name' => 'virtualization', 'label' => 'Virtualización', 'type' => 'text'],
            ],
        ]);

        $softwareType = AssetType::create([
            'name' => 'Software',
            'icon' => 'apps',
            'fields' => [
                ['name' => 'version', 'label' => 'Versión', 'type' => 'text'],
                ['name' => 'license_type', 'label' => 'Tipo de licencia', 'type' => 'select', 'options' => ['Perpetua', 'Suscripción', 'Open Source']],
                ['name' => 'license_key', 'label' => 'Clave de licencia', 'type' => 'text'],
                ['name' => 'seats', 'label' => 'Puestos', 'type' => 'number'],
            ],
        ]);

        $printerType = AssetType::create([
            'name' => 'Impresora',
            'icon' => 'print',
            'fields' => [
                ['name' => 'printer_type', 'label' => 'Tipo', 'type' => 'select', 'options' => ['Láser', 'Inyección', 'Multifunción']],
                ['name' => 'color', 'label' => 'Color', 'type' => 'checkbox'],
            ],
        ]);

        $networkType = AssetType::create([
            'name' => 'Red',
            'icon' => 'router',
            'fields' => [
                ['name' => 'device_type', 'label' => 'Tipo de dispositivo', 'type' => 'select', 'options' => ['Router', 'Switch', 'Access Point', 'Firewall']],
                ['name' => 'ports', 'label' => 'Puertos', 'type' => 'number'],
                ['name' => 'firmware', 'label' => 'Firmware', 'type' => 'text'],
            ],
        ]);

        $dept = \App\Models\Department::first();

        Asset::create([
            'asset_type_id' => $laptopType->id,
            'name' => 'MacBook Pro - Admin Demo',
            'asset_tag' => $assetService->generateAssetTag($tenant),
            'serial_number' => 'C02XL0ZZJGH5',
            'status' => 'active',
            'condition' => 'good',
            'assigned_to' => $adminUser->id,
            'department_id' => $dept?->id,
            'location' => 'Oficina Lima - Piso 3',
            'purchase_date' => '2025-06-15',
            'purchase_cost' => 7500.00,
            'warranty_expiry' => '2027-06-15',
            'vendor' => 'iShop Peru',
            'manufacturer' => 'Apple',
            'model' => 'MacBook Pro 14" M3',
            'custom_fields' => ['ram' => '16GB', 'cpu' => 'Apple M3', 'storage' => '512GB SSD', 'os' => 'macOS Sonoma'],
        ]);

        Asset::create([
            'asset_type_id' => $laptopType->id,
            'name' => 'ThinkPad - Agente Soporte',
            'asset_tag' => $assetService->generateAssetTag($tenant),
            'serial_number' => 'PF2XYZAB',
            'status' => 'active',
            'condition' => 'good',
            'assigned_to' => $agentUser->id,
            'department_id' => $dept?->id,
            'location' => 'Oficina Lima - Piso 2',
            'purchase_date' => '2025-03-01',
            'purchase_cost' => 4200.00,
            'warranty_expiry' => '2028-03-01',
            'vendor' => 'Lenovo Peru',
            'manufacturer' => 'Lenovo',
            'model' => 'ThinkPad T14s Gen 4',
            'custom_fields' => ['ram' => '16GB', 'cpu' => 'Intel i7-1365U', 'storage' => '512GB SSD', 'os' => 'Windows 11 Pro'],
        ]);

        $server = Asset::create([
            'asset_type_id' => $serverType->id,
            'name' => 'Servidor Principal - Producción',
            'asset_tag' => $assetService->generateAssetTag($tenant),
            'serial_number' => 'SRV-2025-001',
            'status' => 'active',
            'condition' => 'good',
            'location' => 'Data Center Lima',
            'purchase_date' => '2024-01-15',
            'purchase_cost' => 25000.00,
            'warranty_expiry' => '2027-01-15',
            'vendor' => 'Dell Technologies',
            'manufacturer' => 'Dell',
            'model' => 'PowerEdge R750',
            'ip_address' => '192.168.1.10',
            'custom_fields' => ['ram' => '64GB', 'cpu' => 'Intel Xeon Gold 5318Y', 'storage' => '2TB NVMe RAID', 'os' => 'Ubuntu 22.04 LTS', 'virtualization' => 'VMware ESXi 8.0'],
        ]);

        Asset::create([
            'asset_type_id' => $softwareType->id,
            'name' => 'Microsoft 365 Business Premium',
            'asset_tag' => $assetService->generateAssetTag($tenant),
            'status' => 'active',
            'condition' => 'good',
            'purchase_date' => '2025-01-01',
            'purchase_cost' => 12000.00,
            'warranty_expiry' => '2026-01-01',
            'vendor' => 'Microsoft',
            'manufacturer' => 'Microsoft',
            'model' => 'M365 Business Premium',
            'custom_fields' => ['version' => '2024', 'license_type' => 'Suscripción', 'license_key' => 'XXXXX-XXXXX-XXXXX', 'seats' => 50],
        ]);

        Asset::create([
            'asset_type_id' => $printerType->id,
            'name' => 'Impresora Piso 2',
            'asset_tag' => $assetService->generateAssetTag($tenant),
            'serial_number' => 'PRN-HP-001',
            'status' => 'active',
            'condition' => 'good',
            'location' => 'Oficina Lima - Piso 2',
            'purchase_date' => '2025-02-01',
            'purchase_cost' => 2500.00,
            'warranty_expiry' => '2027-02-01',
            'vendor' => 'HP Peru',
            'manufacturer' => 'HP',
            'model' => 'LaserJet Pro MFP M428fdw',
            'ip_address' => '192.168.1.50',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'custom_fields' => ['printer_type' => 'Multifunción', 'color' => true],
        ]);

        Asset::create([
            'asset_type_id' => $networkType->id,
            'name' => 'Switch Principal',
            'asset_tag' => $assetService->generateAssetTag($tenant),
            'serial_number' => 'SW-CISCO-001',
            'status' => 'active',
            'condition' => 'good',
            'location' => 'Data Center Lima',
            'purchase_date' => '2024-06-01',
            'purchase_cost' => 8000.00,
            'warranty_expiry' => '2029-06-01',
            'vendor' => 'Cisco',
            'manufacturer' => 'Cisco',
            'model' => 'Catalyst 9300-48P',
            'ip_address' => '192.168.1.1',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'custom_fields' => ['device_type' => 'Switch', 'ports' => 48, 'firmware' => 'IOS XE 17.9'],
        ]);
    }
}
