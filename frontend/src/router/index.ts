import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import NProgress from 'nprogress'
import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

NProgress.configure({ showSpinner: false, speed: 400, minimum: 0.2 })

const routes: RouteRecordRaw[] = [
  {
    path: '/super-admin',
    component: () => import('@/layouts/SuperAdminLayout.vue'),
    meta: { requiresAuth: true, roles: ['super_admin'] },
    children: [
      { path: '', name: 'super-admin-dashboard', component: () => import('@/pages/super-admin/SuperAdminDashboard.vue') },
      { path: 'tenants', name: 'super-admin-tenants', component: () => import('@/pages/super-admin/TenantsPage.vue') },
    ],
  },

  // ─── End-user Portal ───────────────────────────────────────────────
  {
    path: '/portal/:tenantSlug',
    component: () => import('@/layouts/PortalLayout.vue'),
    meta: { isPortal: true },
    children: [
      { path: '', name: 'portal-home', component: () => import('@/pages/portal/PortalHomePage.vue') },
      { path: 'login', name: 'portal-login', component: () => import('@/pages/portal/PortalLoginPage.vue') },
      { path: 'register', name: 'portal-register', component: () => import('@/pages/portal/PortalRegisterPage.vue') },
      { path: 'kb', name: 'portal-kb', component: () => import('@/pages/portal/PortalKbPage.vue') },
      { path: 'kb/:id', name: 'portal-kb-article', component: () => import('@/pages/portal/PortalKbArticlePage.vue'), props: true },
      { path: 'catalog', name: 'portal-catalog', component: () => import('@/pages/portal/PortalCatalogPage.vue') },
      { path: 'tickets', name: 'portal-tickets', component: () => import('@/pages/portal/PortalTicketsPage.vue'), meta: { portalAuth: true } },
      { path: 'tickets/create', name: 'portal-ticket-create', component: () => import('@/pages/portal/PortalTicketCreatePage.vue'), meta: { portalAuth: true } },
      { path: 'tickets/:id', name: 'portal-ticket-detail', component: () => import('@/pages/portal/PortalTicketDetailPage.vue'), props: true, meta: { portalAuth: true } },
    ],
  },

  // ─── Main App (admin/agent) ────────────────────────────────────────
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue') },
      { path: 'tickets', name: 'tickets', component: () => import('@/pages/tickets/TicketListPage.vue') },
      { path: 'tickets/create', name: 'ticket-create', component: () => import('@/pages/tickets/TicketCreatePage.vue') },
      { path: 'tickets/:id', name: 'ticket-detail', component: () => import('@/pages/tickets/TicketDetailPage.vue'), props: true },
      { path: 'kb', name: 'kb', component: () => import('@/pages/kb/KbListPage.vue') },
      { path: 'kb/articles/:id', name: 'kb-article', component: () => import('@/pages/kb/KbArticlePage.vue'), props: true },
      { path: 'kb/editor/:id?', name: 'kb-editor', component: () => import('@/pages/kb/KbEditorPage.vue'), props: true, meta: { roles: ['admin', 'agent'] } },
      { path: 'catalog', name: 'catalog', component: () => import('@/pages/catalog/CatalogPage.vue') },
      { path: 'settings', name: 'settings', component: () => import('@/pages/settings/SettingsPage.vue'), meta: { roles: ['admin'] } },
      { path: 'settings/users', name: 'users', component: () => import('@/pages/settings/UsersPage.vue'), meta: { roles: ['admin'] } },
      { path: 'settings/categories', name: 'categories', component: () => import('@/pages/settings/CategoriesPage.vue'), meta: { roles: ['admin'] } },
      { path: 'settings/sla', name: 'sla', component: () => import('@/pages/settings/SlaPage.vue'), meta: { roles: ['admin'] } },
      { path: 'settings/ticket-form', name: 'ticket-form-config', component: () => import('@/pages/settings/TicketFormConfigPage.vue'), meta: { roles: ['admin'] } },
      { path: 'reports', name: 'reports', component: () => import('@/pages/ReportsPage.vue'), meta: { roles: ['admin'] } },
      { path: 'search', name: 'search', component: () => import('@/pages/SearchResultsPage.vue') },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/pages/profile/ProfilePage.vue'),
        meta: { title: 'Perfil' },
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: 'login', name: 'login', component: () => import('@/pages/auth/LoginPage.vue') },
      { path: 'register', name: 'register', component: () => import('@/pages/auth/RegisterPage.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  NProgress.start()
  const auth = useAuthStore(pinia)

  // ─── Portal routes ──────────────────────────────────────────────────
  if (to.meta.isPortal || to.matched.some(r => r.meta.isPortal)) {
    // Portal auth-required pages: check for token
    if (to.meta.portalAuth) {
      const token = localStorage.getItem('token')
      const slug = to.params.tenantSlug as string
      if (!token) {
        return { name: 'portal-login', params: { tenantSlug: slug }, query: { redirect: to.fullPath } }
      }
    }
    // Allow all other portal pages (public)
    return
  }

  // ─── Main app routes ────────────────────────────────────────────────
  if (!auth.isAuthenticated && localStorage.getItem('token')) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Super admin redirect: if they go to /dashboard, send them to /super-admin
  if (to.path === '/dashboard' && auth.user?.role === 'super_admin') {
    return { name: 'super-admin-dashboard' }
  }

  // End-user redirect: send them to portal instead of admin dashboard
  if (to.path === '/dashboard' && auth.user?.role === 'end_user' && auth.user?.tenant?.slug) {
    return { path: `/portal/${auth.user.tenant.slug}` }
  }

  if ((to.name === 'login' || to.name === 'register') && auth.isAuthenticated) {
    if (auth.user?.role === 'super_admin') {
      return { name: 'super-admin-dashboard' }
    }
    // End-user: redirect to portal
    if (auth.user?.role === 'end_user' && auth.user?.tenant?.slug) {
      return { path: `/portal/${auth.user.tenant.slug}` }
    }
    return { name: 'dashboard' }
  }

  if (to.meta.roles && auth.user) {
    const roles = to.meta.roles as string[]
    if (!roles.includes(auth.user.role)) {
      if (auth.user.role === 'super_admin') {
        return { name: 'super-admin-dashboard' }
      }
      if (auth.user.role === 'end_user' && auth.user.tenant?.slug) {
        return { path: `/portal/${auth.user.tenant.slug}` }
      }
      return { name: 'dashboard' }
    }
  }
})

router.afterEach(() => {
  NProgress.done()
})

export default router
