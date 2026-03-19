<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatbotController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\SlaPolicyController;
use App\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Http\Controllers\Api\V1\ServiceCatalogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\InboundEmailController;
use App\Http\Controllers\Api\V1\TicketFormFieldController;
use App\Http\Controllers\Api\V1\TicketViewController;
use App\Http\Controllers\Api\V1\TenantManagementController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\TicketAssociationController;
use App\Http\Controllers\Api\V1\AgentGroupController;
use App\Http\Controllers\Api\V1\ScenarioController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Broadcasting auth endpoint (uses Passport token)
Broadcast::routes(['middleware' => ['auth:api'], 'prefix' => 'api/v1']);

Route::prefix('v1')->group(function () {
    // Public auth
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Public tenant info (for subdomain detection)
    Route::get('tenant-info', [AuthController::class, 'tenantInfo']);

    // Protected routes
    Route::middleware(['auth:api', 'tenant'])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'changePassword']);
        Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar']);

        // Tickets
        Route::put('tickets/bulk-update', [TicketController::class, 'bulkUpdate'])
            ->middleware('role:admin,agent');
        Route::post('tickets/export', [TicketController::class, 'export'])
            ->middleware('role:admin,agent');
        Route::get('tickets/tags/list', [TicketController::class, 'tags']);
        Route::patch('tickets/{ticket}/quick-update', [TicketController::class, 'quickUpdate']);
        Route::apiResource('tickets', TicketController::class);
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign']);
        Route::post('tickets/{ticket}/close', [TicketController::class, 'close']);
        Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen']);
        Route::post('tickets/{ticket}/comments', [TicketController::class, 'addComment']);
        Route::post('tickets/{ticket}/attachments', [TicketController::class, 'addAttachments']);
        Route::delete('tickets/{ticket}/attachments/{attachment}', [TicketController::class, 'deleteAttachment']);

        // Time entries
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('tickets/{ticket}/time-entries', [TimeEntryController::class, 'index']);
            Route::post('tickets/{ticket}/time-entries', [TimeEntryController::class, 'store']);
            Route::put('tickets/{ticket}/time-entries/{entry}', [TimeEntryController::class, 'update']);
            Route::delete('tickets/{ticket}/time-entries/{entry}', [TimeEntryController::class, 'destroy']);
        });

        // Ticket associations
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('tickets/{ticket}/associations', [TicketAssociationController::class, 'index']);
            Route::post('tickets/{ticket}/associations', [TicketAssociationController::class, 'store']);
            Route::delete('tickets/{ticket}/associations/{association}', [TicketAssociationController::class, 'destroy']);
        });

        // Ticket merge, spam, favorite
        Route::post('tickets/{ticket}/merge', [TicketController::class, 'merge'])
            ->middleware('role:admin,agent');
        Route::post('tickets/{ticket}/spam', [TicketController::class, 'toggleSpam'])
            ->middleware('role:admin,agent');
        Route::post('tickets/{ticket}/favorite', [TicketController::class, 'toggleFavorite']);

        // Scenarios
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('scenarios', [ScenarioController::class, 'index']);
            Route::post('scenarios', [ScenarioController::class, 'store']);
            Route::put('scenarios/{scenario}', [ScenarioController::class, 'update']);
            Route::delete('scenarios/{scenario}', [ScenarioController::class, 'destroy']);
            Route::post('tickets/{ticket}/run-scenario', [ScenarioController::class, 'execute']);
        });

        // Agent Groups
        Route::get('agent-groups', [AgentGroupController::class, 'index']);
        Route::middleware('role:admin')->group(function () {
            Route::post('agent-groups', [AgentGroupController::class, 'store']);
            Route::get('agent-groups/{agentGroup}', [AgentGroupController::class, 'show']);
            Route::put('agent-groups/{agentGroup}', [AgentGroupController::class, 'update']);
            Route::delete('agent-groups/{agentGroup}', [AgentGroupController::class, 'destroy']);
        });

        // Ticket Views (saved filters)
        Route::apiResource('ticket-views', TicketViewController::class)->except(['show']);

        // Categories (admin)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('categories', CategoryController::class);
        });
        // Allow reading categories for all authenticated users
        Route::get('categories', [CategoryController::class, 'index']);

        // Departments
        Route::get('departments', [DepartmentController::class, 'index']);
        Route::get('departments/{department}', [DepartmentController::class, 'show']);
        Route::middleware('role:admin')->group(function () {
            Route::post('departments', [DepartmentController::class, 'store']);
            Route::put('departments/{department}', [DepartmentController::class, 'update']);
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);
        });

        // SLA Policies (admin)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('sla-policies', SlaPolicyController::class);
        });

        // Knowledge Base
        Route::prefix('kb')->group(function () {
            Route::get('categories', [KnowledgeBaseController::class, 'categories']);
            Route::get('articles', [KnowledgeBaseController::class, 'articles']);
            Route::get('articles/{article}', [KnowledgeBaseController::class, 'showArticle']);
            Route::post('articles/{article}/helpful', [KnowledgeBaseController::class, 'helpful']);

            // KB management (admin/agent)
            Route::middleware('role:admin,agent')->group(function () {
                Route::post('categories', [KnowledgeBaseController::class, 'storeCategory']);
                Route::put('categories/{category}', [KnowledgeBaseController::class, 'updateCategory']);
                Route::delete('categories/{category}', [KnowledgeBaseController::class, 'destroyCategory']);
                Route::post('articles', [KnowledgeBaseController::class, 'storeArticle']);
                Route::put('articles/{article}', [KnowledgeBaseController::class, 'updateArticle']);
                Route::delete('articles/{article}', [KnowledgeBaseController::class, 'destroyArticle']);
            });
        });

        // Service Catalog
        Route::get('catalog', [ServiceCatalogController::class, 'index']);
        Route::get('catalog/{catalogItem}', [ServiceCatalogController::class, 'show']);
        Route::post('catalog/{catalogItem}/request', [ServiceCatalogController::class, 'request']);
        Route::middleware('role:admin')->group(function () {
            Route::post('catalog', [ServiceCatalogController::class, 'store']);
            Route::put('catalog/{catalogItem}', [ServiceCatalogController::class, 'update']);
            Route::delete('catalog/{catalogItem}', [ServiceCatalogController::class, 'destroy']);
        });

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('summary', [DashboardController::class, 'summary']);
            Route::get('tickets-by-status', [DashboardController::class, 'ticketsByStatus']);
            Route::get('tickets-by-priority', [DashboardController::class, 'ticketsByPriority']);
            Route::get('trends', [DashboardController::class, 'trends']);
            Route::get('agent-performance', [DashboardController::class, 'agentPerformance'])
                ->middleware('role:admin');
        });

        // Users (admin)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('users', UserController::class);
        });
        Route::get('users/agents/list', [UserController::class, 'agents']);
        Route::get('users/{user}/recent-tickets', [UserController::class, 'recentTickets']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);

        // Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        // AI endpoints (for tickets)
        Route::post('tickets/{ticket}/classify', [AiController::class, 'classify']);
        Route::post('tickets/{ticket}/suggest-response', [AiController::class, 'suggestResponse']);
        Route::post('tickets/{ticket}/generate-kb', [AiController::class, 'generateKbArticle'])
            ->middleware('role:admin,agent');
        Route::post('ai/improve-text', [AiController::class, 'improveText'])
            ->middleware('role:admin,agent');

        // Settings (tenant admin)
        Route::middleware('role:admin')->prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'show']);
            Route::put('/', [SettingsController::class, 'update']);
            Route::put('/domain', [SettingsController::class, 'updateDomain']);
            Route::get('/verify-domain', [SettingsController::class, 'verifyDomain']);
            Route::post('/branding/logo', [SettingsController::class, 'uploadLogo']);
            Route::delete('/branding/logo', [SettingsController::class, 'deleteLogo']);
            Route::post('/branding/favicon', [SettingsController::class, 'uploadFavicon']);
            Route::delete('/branding/favicon', [SettingsController::class, 'deleteFavicon']);
            Route::put('/branding/colors', [SettingsController::class, 'updateBrandColors']);
        });

        // Ticket Form Config
        Route::get('ticket-form-fields', [TicketFormFieldController::class, 'index']);
        Route::middleware('role:admin')->group(function () {
            Route::put('ticket-form-fields/bulk', [TicketFormFieldController::class, 'bulkUpdate']);
            Route::post('ticket-form-fields/custom', [TicketFormFieldController::class, 'storeCustom']);
            Route::delete('ticket-form-fields/{field}', [TicketFormFieldController::class, 'destroyCustom']);
        });

        // Super Admin routes
        Route::middleware('super_admin')->prefix('admin')->group(function () {
            Route::get('stats', [TenantManagementController::class, 'platformStats']);
            Route::get('tenants', [TenantManagementController::class, 'index']);
            Route::post('tenants', [TenantManagementController::class, 'store']);
            Route::get('tenants/{tenant}', [TenantManagementController::class, 'show']);
            Route::put('tenants/{tenant}', [TenantManagementController::class, 'update']);
            Route::delete('tenants/{tenant}', [TenantManagementController::class, 'destroy']);
            Route::post('tenants/{tenant}/toggle-active', [TenantManagementController::class, 'toggleActive']);
            Route::get('tenants/{tenant}/users', [TenantManagementController::class, 'users']);
            Route::post('tenants/{tenant}/impersonate', [TenantManagementController::class, 'impersonate']);
        });
    });

    // ─── End-user Portal (public routes) ────────────────────────────────
    Route::prefix('portal/{tenantSlug}')->group(function () {
        Route::get('info', [PortalController::class, 'tenantInfo']);
        Route::post('login', [PortalController::class, 'login']);
        Route::post('register', [PortalController::class, 'register']);
        Route::get('kb/categories', [PortalController::class, 'kbCategories']);
        Route::get('kb/articles', [PortalController::class, 'kbArticles']);
        Route::get('kb/articles/{article}', [PortalController::class, 'kbArticle']);
        Route::get('catalog', [PortalController::class, 'catalog']);
    });

    // Public chatbot routes
    Route::post('chatbot/{tenantSlug}/message', [ChatbotController::class, 'message']);
    Route::post('chatbot/{tenantSlug}/create-ticket', [ChatbotController::class, 'createTicket']);

    // Inbound email webhook (public, verified by secret)
    Route::post('inbound-email/webhook', [InboundEmailController::class, 'webhook']);
});
