<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AssetTypeController;
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
use App\Http\Controllers\Api\V1\BusinessHourController;
use App\Http\Controllers\Api\V1\ScenarioController;
use App\Http\Controllers\Api\V1\AutomationRuleController;
use App\Http\Controllers\Api\V1\CannedResponseController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\ApprovalWorkflowController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\SatisfactionSurveyController;
use App\Http\Controllers\Api\V1\ProblemController;
use App\Http\Controllers\Api\V1\KnownErrorController;
use App\Http\Controllers\Api\V1\ChangeRequestController;
use App\Http\Controllers\Api\V1\IncomingWebhookController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Broadcasting auth endpoint (uses Passport token)
Broadcast::routes(['middleware' => ['auth:api'], 'prefix' => 'api/v1']);

Route::prefix('v1')->group(function () {
    // Public auth (rate limited: 5 attempts/min)
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Public tenant info (for subdomain detection)
    Route::get('tenant-info', [AuthController::class, 'tenantInfo']);

    // Protected routes (rate limited: 60 req/min)
    Route::middleware(['auth:api', 'tenant', 'plan.limits', 'throttle:api'])->group(function () {
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

        // Ticket merge, spam, favorite, share
        Route::post('tickets/{ticket}/merge', [TicketController::class, 'merge'])
            ->middleware('role:admin,agent');
        Route::post('tickets/{ticket}/spam', [TicketController::class, 'toggleSpam'])
            ->middleware('role:admin,agent');
        Route::post('tickets/{ticket}/share', [TicketController::class, 'share']);
        Route::post('tickets/{ticket}/favorite', [TicketController::class, 'toggleFavorite']);

        // Scenarios
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('scenarios', [ScenarioController::class, 'index']);
            Route::post('scenarios', [ScenarioController::class, 'store']);
            Route::put('scenarios/{scenario}', [ScenarioController::class, 'update']);
            Route::delete('scenarios/{scenario}', [ScenarioController::class, 'destroy']);
            Route::post('tickets/{ticket}/run-scenario', [ScenarioController::class, 'execute']);
        });

        // Automation Rules (admin only)
        Route::middleware('role:admin')->group(function () {
            Route::get('automation-rules/available-fields', [AutomationRuleController::class, 'availableFields']);
            Route::get('automation-rules/templates', [AutomationRuleController::class, 'templates']);
            Route::post('automation-rules/reorder', [AutomationRuleController::class, 'reorder']);
            Route::post('automation-rules/{id}/toggle', [AutomationRuleController::class, 'toggle']);
            Route::post('automation-rules/{id}/test/{ticketId}', [AutomationRuleController::class, 'test']);
            Route::get('automation-rules/{id}/logs', [AutomationRuleController::class, 'logs']);
            Route::apiResource('automation-rules', AutomationRuleController::class);
        });

        // Canned Responses
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('canned-responses/search', [CannedResponseController::class, 'search']);
            Route::post('canned-responses/{cannedResponse}/use', [CannedResponseController::class, 'use']);
            Route::apiResource('canned-responses', CannedResponseController::class);
        });

        // Agent Groups
        Route::get('agent-groups', [AgentGroupController::class, 'index']);
        Route::middleware('role:admin')->group(function () {
            Route::post('agent-groups', [AgentGroupController::class, 'store']);
            Route::get('agent-groups/{agentGroup}', [AgentGroupController::class, 'show']);
            Route::put('agent-groups/{agentGroup}', [AgentGroupController::class, 'update']);
            Route::delete('agent-groups/{agentGroup}', [AgentGroupController::class, 'destroy']);
        });

        // Problems (ITIL Problem Management)
        Route::middleware('role:admin,agent')->group(function () {
            Route::apiResource('problems', ProblemController::class);
            Route::post('problems/{id}/link-tickets', [ProblemController::class, 'linkTickets']);
            Route::delete('problems/{id}/unlink-ticket/{ticketId}', [ProblemController::class, 'unlinkTicket']);
            Route::post('problems/{id}/promote-known-error', [ProblemController::class, 'promoteToKnownError']);
            Route::put('problems/{id}/root-cause', [ProblemController::class, 'updateRootCause']);
            Route::post('problems/{id}/resolve', [ProblemController::class, 'resolve']);
            Route::post('problems/{id}/close', [ProblemController::class, 'close']);
        });

        // Known Errors
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('known-errors/search', [KnownErrorController::class, 'search']);
            Route::apiResource('known-errors', KnownErrorController::class);
        });

        // Change Requests (ITIL Change Management)
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('change-requests/calendar', [ChangeRequestController::class, 'calendar']);
            Route::apiResource('change-requests', ChangeRequestController::class);
            Route::post('change-requests/{id}/submit', [ChangeRequestController::class, 'submit']);
            Route::post('change-requests/{id}/assess-risk', [ChangeRequestController::class, 'assessRisk']);
            Route::post('change-requests/{id}/request-cab-review', [ChangeRequestController::class, 'requestCabReview']);
            Route::post('change-requests/{id}/approve-cab', [ChangeRequestController::class, 'approveCab']);
            Route::post('change-requests/{id}/reject-cab', [ChangeRequestController::class, 'rejectCab']);
            Route::post('change-requests/{id}/schedule', [ChangeRequestController::class, 'schedule']);
            Route::post('change-requests/{id}/start-implementation', [ChangeRequestController::class, 'startImplementation']);
            Route::post('change-requests/{id}/complete-implementation', [ChangeRequestController::class, 'completeImplementation']);
            Route::post('change-requests/{id}/close-review', [ChangeRequestController::class, 'closeReview']);
            Route::post('change-requests/{id}/link-tickets', [ChangeRequestController::class, 'linkTickets']);
        });

        // Asset Types (admin)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('asset-types', AssetTypeController::class);
        });
        // Allow reading asset types for admin/agent
        Route::get('asset-types', [AssetTypeController::class, 'index'])->middleware('role:admin,agent');

        // Assets (CMDB)
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('assets/dashboard/stats', [AssetController::class, 'dashboard']);
            Route::get('assets/next-tag', [AssetController::class, 'nextTag']);
            Route::post('assets/export', [AssetController::class, 'export']);
            Route::apiResource('assets', AssetController::class);
            Route::post('assets/{id}/assign', [AssetController::class, 'assign']);
            Route::post('assets/{id}/unassign', [AssetController::class, 'unassign']);
            Route::post('assets/{id}/link-ticket/{ticketId}', [AssetController::class, 'linkTicket']);
            Route::delete('assets/{id}/unlink-ticket/{ticketId}', [AssetController::class, 'unlinkTicket']);
            Route::get('assets/{id}/relationships', [AssetController::class, 'relationships']);
            Route::post('assets/{id}/relationships', [AssetController::class, 'addRelationship']);
            Route::delete('assets/{id}/relationships/{relId}', [AssetController::class, 'removeRelationship']);
            Route::get('assets/{id}/timeline', [AssetController::class, 'timeline']);
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
        Route::middleware(['role:admin', 'check.feature:sla_policies'])->group(function () {
            Route::apiResource('sla-policies', SlaPolicyController::class);
        });

        // Business Hours (admin)
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('business-hours', BusinessHourController::class);
            Route::get('business-hours/{businessHour}/holidays', [BusinessHourController::class, 'holidayIndex']);
            Route::post('business-hours/{businessHour}/holidays', [BusinessHourController::class, 'holidayStore']);
            Route::put('business-hours/{businessHour}/holidays/{holiday}', [BusinessHourController::class, 'holidayUpdate']);
            Route::delete('business-hours/{businessHour}/holidays/{holiday}', [BusinessHourController::class, 'holidayDestroy']);
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
        Route::middleware('check.feature:service_catalog')->group(function () {
            Route::get('catalog', [ServiceCatalogController::class, 'index']);
            Route::get('catalog/{catalogItem}', [ServiceCatalogController::class, 'show']);
            Route::post('catalog/{catalogItem}/request', [ServiceCatalogController::class, 'request']);
            Route::middleware('role:admin')->group(function () {
                Route::post('catalog', [ServiceCatalogController::class, 'store']);
                Route::put('catalog/{catalogItem}', [ServiceCatalogController::class, 'update']);
                Route::delete('catalog/{catalogItem}', [ServiceCatalogController::class, 'destroy']);
            });
        });

        // Approval Workflows (admin only)
        Route::apiResource('approval-workflows', ApprovalWorkflowController::class)->middleware('role:admin');

        // Approvals
        Route::get('approvals/my-pending', [ApprovalController::class, 'myPending']);
        Route::get('approvals', [ApprovalController::class, 'index'])->middleware('role:admin,agent');
        Route::get('approvals/{id}', [ApprovalController::class, 'show']);
        Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve']);
        Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject']);

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('summary', [DashboardController::class, 'summary']);
            Route::get('tickets-by-status', [DashboardController::class, 'ticketsByStatus']);
            Route::get('tickets-by-priority', [DashboardController::class, 'ticketsByPriority']);
            Route::get('trends', [DashboardController::class, 'trends']);
            Route::get('agent-performance', [DashboardController::class, 'agentPerformance'])
                ->middleware('role:admin');
        });

        // Reports
        Route::middleware('role:admin,agent')->group(function () {
            Route::get('reports/templates', [ReportController::class, 'templates']);
            Route::get('reports/available-fields', [ReportController::class, 'availableFields']);
            Route::post('reports/preview', [ReportController::class, 'preview']);
            Route::post('reports/{id}/execute', [ReportController::class, 'execute']);
            Route::get('reports/{id}/export', [ReportController::class, 'export']);
            Route::apiResource('reports', ReportController::class);
        });

        // Satisfaction Surveys (protected)
        Route::get('satisfaction-surveys/stats', [SatisfactionSurveyController::class, 'stats'])
            ->middleware('role:admin');
        Route::get('satisfaction-surveys', [SatisfactionSurveyController::class, 'index'])
            ->middleware('role:admin,agent');

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

        // Notification Preferences
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);

        // Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        // AI endpoints (for tickets)
        Route::post('tickets/{ticket}/classify', [AiController::class, 'classify'])
            ->middleware('check.feature:ai_classification');
        Route::post('tickets/{ticket}/suggest-response', [AiController::class, 'suggestResponse'])
            ->middleware('check.feature:ai_suggestions');
        Route::post('tickets/{ticket}/generate-kb', [AiController::class, 'generateKbArticle'])
            ->middleware(['role:admin,agent', 'check.feature:ai_suggestions']);
        Route::post('ai/improve-text', [AiController::class, 'improveText'])
            ->middleware(['role:admin,agent', 'check.feature:ai_suggestions']);

        // Settings (tenant admin)
        Route::middleware('role:admin')->prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'show']);
            Route::put('/', [SettingsController::class, 'update']);
            Route::put('/domain', [SettingsController::class, 'updateDomain'])
                ->middleware('check.feature:custom_domain');
            Route::get('/verify-domain', [SettingsController::class, 'verifyDomain'])
                ->middleware('check.feature:custom_domain');
            Route::post('/branding/logo', [SettingsController::class, 'uploadLogo']);
            Route::delete('/branding/logo', [SettingsController::class, 'deleteLogo']);
            Route::post('/branding/favicon', [SettingsController::class, 'uploadFavicon']);
            Route::delete('/branding/favicon', [SettingsController::class, 'deleteFavicon']);
            Route::put('/branding/colors', [SettingsController::class, 'updateBrandColors']);
        });

        // Integrations (Slack, Teams, webhooks) - admin only
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('integrations', IntegrationController::class);
            Route::post('integrations/{integration}/test', [IntegrationController::class, 'test']);
        });

        // Ticket Form Config
        Route::get('ticket-form-fields', [TicketFormFieldController::class, 'index']);
        Route::middleware('role:admin')->group(function () {
            Route::put('ticket-form-fields/bulk', [TicketFormFieldController::class, 'bulkUpdate']);
            Route::post('ticket-form-fields/custom', [TicketFormFieldController::class, 'storeCustom']);
            Route::delete('ticket-form-fields/{field}', [TicketFormFieldController::class, 'destroyCustom']);
        });

        // Plan usage (tenant admin)
        Route::middleware('role:admin')->group(function () {
            Route::get('plan/usage', [TenantManagementController::class, 'planUsage']);
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
            Route::put('tenants/{tenant}/plan', [TenantManagementController::class, 'updatePlan']);
            Route::get('tenants/{tenant}/usage', [TenantManagementController::class, 'tenantUsage']);
        });
    });

    // ─── End-user Portal (public routes) ────────────────────────────────
    Route::prefix('portal/{tenantSlug}')->group(function () {
        Route::get('info', [PortalController::class, 'tenantInfo']);
        Route::post('login', [PortalController::class, 'login'])->middleware('throttle:auth');
        Route::post('register', [PortalController::class, 'register'])->middleware('throttle:auth');
        Route::get('kb/categories', [PortalController::class, 'kbCategories']);
        Route::get('kb/articles', [PortalController::class, 'kbArticles']);
        Route::get('kb/articles/{article}', [PortalController::class, 'kbArticle']);
        Route::get('catalog', [PortalController::class, 'catalog']);
    });

    // Public chatbot routes (feature check handled in controller)
    Route::post('chatbot/{tenantSlug}/message', [ChatbotController::class, 'message']);
    Route::post('chatbot/{tenantSlug}/create-ticket', [ChatbotController::class, 'createTicket']);

    // Public satisfaction survey routes (token-based, no auth)
    Route::get('survey/{token}', [SatisfactionSurveyController::class, 'show']);
    Route::post('survey/{token}', [SatisfactionSurveyController::class, 'respond']);

    // Inbound email webhook (public, verified by secret)
    Route::post('inbound-email/webhook', [InboundEmailController::class, 'webhook']);

    // Incoming webhooks from Slack/Teams (public, verified by signature)
    Route::post('webhooks/slack/{tenantSlug}', [IncomingWebhookController::class, 'slack']);
    Route::post('webhooks/teams/{tenantSlug}', [IncomingWebhookController::class, 'teams']);
});
