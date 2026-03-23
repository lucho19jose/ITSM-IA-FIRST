<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuasar, Notify } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { applyTenantTheme } from '@/utils/theme'
import { updateProfile } from '@/api/profile'
import { getEcho, disconnectEcho } from '@/utils/echo'
import { get } from '@/utils/api'
import { getNotifications, markNotificationRead } from '@/api/notifications'
import { playNewTicket, playNotification, isSoundEnabled, setSoundEnabled } from '@/utils/sounds'
import { resetOnboarding } from '@/composables/useOnboarding'
import { getMyPendingApprovals } from '@/api/approvals'
import RecentActivitiesDrawer from '@/components/RecentActivitiesDrawer.vue'

const { t } = useI18n()
const $q = useQuasar()
const router = useRouter()
const auth = useAuthStore()
const leftDrawerOpen = ref(true)
const globalSearch = ref('')

function onGlobalSearch() {
  if (!globalSearch.value.trim()) return
  router.push({ path: '/search', query: { term: globalSearch.value.trim() } })
  globalSearch.value = ''
}

// Dynamic favicon based on tenant branding
watch(
  () => auth.tenant?.favicon_url,
  (url) => {
    const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (link) link.href = url || '/vite.svg'
  },
  { immediate: true }
)

// Dynamic brand colors
watch(
  () => auth.tenant?.settings,
  (settings) => applyTenantTheme(settings),
  { immediate: true, deep: true }
)

const menuItems = [
  { icon: 'dashboard', label: 'nav.dashboard', to: '/dashboard' },
  { icon: 'confirmation_number', label: 'nav.tickets', to: '/tickets' },
  { icon: 'bug_report', label: 'nav.problems', to: '/problems' },
  { icon: 'swap_horiz', label: 'nav.changes', to: '/changes' },
  { icon: 'calendar_month', label: 'nav.changeCalendar', to: '/changes/calendar' },
  { icon: 'warning', label: 'nav.knownErrors', to: '/problems/known-errors' },
  { icon: 'inventory_2', label: 'nav.assets', to: '/assets' },
  { icon: 'menu_book', label: 'nav.kb', to: '/kb' },
  { icon: 'storefront', label: 'nav.catalog', to: '/catalog' },
  { icon: 'fact_check', label: 'nav.approvals', to: '/approvals', badge: true },
]

const adminItems = [
  { icon: 'people', label: 'nav.users', to: '/settings/users' },
  { icon: 'category', label: 'nav.categories', to: '/settings/categories' },
  { icon: 'timer', label: 'nav.sla', to: '/settings/sla' },
  { icon: 'dynamic_form', label: 'nav.ticketForm', to: '/settings/ticket-form' },
  { icon: 'quickreply', label: 'nav.cannedResponses', to: '/settings/canned-responses' },
  { icon: 'devices', label: 'nav.assetTypes', to: '/settings/asset-types' },
  { icon: 'hub', label: 'nav.integrations', to: '/settings/integrations' },
  { icon: 'smart_toy', label: 'nav.automation', to: '/settings/automation-rules' },
  { icon: 'approval', label: 'nav.approvalWorkflows', to: '/settings/approval-workflows' },
  { icon: 'settings', label: 'nav.settings', to: '/settings' },
  { icon: 'assessment', label: 'nav.reports', to: '/reports' },
]

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

// ─── Dark mode (auto / light / dark) ─────────────────────────────────────────
type ThemeMode = 'auto' | 'light' | 'dark'
const themeMode = ref<ThemeMode>('auto')

const systemDarkQuery = window.matchMedia('(prefers-color-scheme: dark)')

function applyTheme(mode: ThemeMode) {
  themeMode.value = mode
  localStorage.setItem('autoservice_theme', mode)
  if (mode === 'auto') {
    $q.dark.set(systemDarkQuery.matches)
  } else {
    $q.dark.set(mode === 'dark')
  }
}

function onSystemThemeChange(e: MediaQueryListEvent) {
  if (themeMode.value === 'auto') {
    $q.dark.set(e.matches)
  }
}

onMounted(() => {
  // Migrate old key
  const oldKey = localStorage.getItem('autoservice_dark_mode')
  if (oldKey !== null) {
    localStorage.removeItem('autoservice_dark_mode')
    localStorage.setItem('autoservice_theme', oldKey === '1' ? 'dark' : 'light')
  }

  const saved = (localStorage.getItem('autoservice_theme') as ThemeMode) || 'auto'
  applyTheme(saved)

  systemDarkQuery.addEventListener('change', onSystemThemeChange)
})

// ─── Notifications ──────────────────────────────────────────────────────────
const unreadCount = ref(0)
const notifications = ref<any[]>([])
const notifLoading = ref(false)
const showNotifPanel = ref(false)

async function loadNotifications() {
  notifLoading.value = true
  try {
    const res = await getNotifications({ per_page: 20 })
    notifications.value = res.data || []
  } catch { /* ignore */ }
  finally { notifLoading.value = false }
}

async function onMarkRead(notif: any) {
  if (notif.read_at) return
  try {
    await markNotificationRead(notif.id)
    notif.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch { /* ignore */ }
}

function onNotifClick(notif: any) {
  onMarkRead(notif)
  showNotifPanel.value = false
  // Navigate based on notification type
  const data = typeof notif.data === 'string' ? JSON.parse(notif.data) : notif.data
  if (data?.ticket_id) {
    router.push(`/tickets/${data.ticket_id}`)
  }
}

function onOpenNotifPanel() {
  showNotifPanel.value = true
  loadNotifications()
}

function notifTimeAgo(dateStr: string): string {
  const diffMin = Math.floor((Date.now() - new Date(dateStr).getTime()) / 60000)
  if (diffMin < 1) return 'ahora'
  if (diffMin < 60) return `hace ${diffMin}m`
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24) return `hace ${diffH}h`
  const diffD = Math.floor(diffH / 24)
  return `hace ${diffD}d`
}

function getNotifIcon(notif: any): string {
  const data = typeof notif.data === 'string' ? JSON.parse(notif.data) : notif.data
  const type = data?.type || ''
  if (type.includes('created') || type.includes('Created')) return 'confirmation_number'
  if (type.includes('comment') || type.includes('Comment')) return 'chat'
  if (type.includes('assigned') || type.includes('Assigned')) return 'person_add'
  if (type.includes('updated') || type.includes('Updated')) return 'edit'
  if (type.includes('sla') || type.includes('SLA') || type.includes('breach')) return 'alarm'
  return 'notifications'
}

function getNotifMessage(notif: any): string {
  const data = typeof notif.data === 'string' ? JSON.parse(notif.data) : notif.data
  return data?.message || data?.title || 'Nueva notificacion'
}

// ─── Real-time WebSocket connection ──────────────────────────────────────────
onMounted(async () => {
  // Fetch initial unread count
  try {
    const res = await get<{ data: { count: number } }>('notifications/unread-count')
    unreadCount.value = res.data?.count ?? 0
  } catch { /* ignore */ }

  // Connect to WebSocket channels
  if (auth.user?.tenant_id) {
    const echo = getEcho()

    if (echo) {
      // Tenant channel: new tickets, updates
      echo.private(`tenant.${auth.user.tenant_id}`)
        .listen('TicketCreated', (e: any) => {
          playNewTicket()
          Notify.create({
            type: 'info',
            message: `Nuevo ticket: ${e.ticket_number} — ${e.title}`,
            icon: 'confirmation_number',
            timeout: 5000,
            actions: [{ label: 'Ver', color: 'white', handler: () => router.push(`/tickets/${e.id}`) }],
          })
        })

      // User channel: personal notifications
      echo.private(`user.${auth.user.id}`)
        .listen('NotificationCreated', () => {
          playNotification()
          unreadCount.value++
        })
    }
  }
})

onUnmounted(() => {
  disconnectEcho()
})

// ─── Auto-assignment availability ─────────────────────────────────────────────
const isAvailableForAssignment = ref(auth.user?.is_available_for_assignment ?? true)

async function toggleAvailability(val: boolean) {
  try {
    await updateProfile({ is_available_for_assignment: val })
  } catch {
    isAvailableForAssignment.value = !val
  }
}

// ─── Sound notifications ─────────────────────────────────────────────────────
const soundEnabled = ref(isSoundEnabled())

function toggleSound(val: boolean) {
  setSoundEnabled(val)
  soundEnabled.value = val
}

const showActivities = ref(false)

// ─── Pending approvals badge ─────────────────────────────────────────────────
const pendingApprovalsCount = ref(0)

onMounted(async () => {
  try {
    const res = await getMyPendingApprovals()
    pendingApprovalsCount.value = res.data?.length ?? 0
  } catch { /* ignore */ }
})

function restartTour() {
  resetOnboarding()
  window.location.reload()
}
</script>

<template>
  <q-layout view="hHh LpR fFf">
    <q-header elevated class="bg-primary">
      <q-toolbar>
        <q-btn flat dense round icon="menu" @click="leftDrawerOpen = !leftDrawerOpen" />
        <q-toolbar-title class="gt-sm">AutoService</q-toolbar-title>
        <q-space />

        <!-- Global search (Freshservice style) -->
        <q-input
          v-model="globalSearch"
          placeholder="Buscar"
          dense outlined rounded
          bg-color="white"
          input-class="text-dark"
          class="header-search"
          @keyup.enter="onGlobalSearch"
        >
          <template v-slot:prepend>
            <q-icon name="search" size="18px" color="grey-6" />
          </template>
          <template v-slot:append>
            <span class="search-shortcut">/</span>
          </template>
        </q-input>

        <q-space />

        <!-- Create button -->
        <q-btn
          flat
          icon="add"
          label="Crear"
          no-caps dense
          color="white"
          class="q-mr-sm gt-xs create-btn"
          to="/tickets/create"
        />

        <q-btn flat round icon="history" color="white" @click="showActivities = true">
          <q-tooltip>Actividades recientes</q-tooltip>
        </q-btn>

        <q-btn flat round icon="notifications" color="white" @click="onOpenNotifPanel">
          <q-badge v-if="unreadCount > 0" color="red" floating>{{ unreadCount }}</q-badge>

          <q-menu v-model="showNotifPanel" anchor="bottom right" self="top right" style="width: 380px; max-height: 480px;" class="notif-menu">
            <!-- Header -->
            <div class="row items-center q-px-md q-py-sm" style="border-bottom: 1px solid #e0e0e0;">
              <div class="text-subtitle1 text-weight-bold">Notificaciones</div>
              <q-space />
              <q-badge v-if="unreadCount > 0" color="primary" :label="`${unreadCount} sin leer`" />
            </div>

            <!-- Loading -->
            <q-linear-progress v-if="notifLoading" indeterminate color="primary" />

            <!-- Empty state -->
            <div v-if="!notifLoading && notifications.length === 0" class="text-center q-pa-xl text-grey-5">
              <q-icon name="notifications_none" size="48px" class="q-mb-sm" />
              <div class="text-body2">Sin notificaciones</div>
            </div>

            <!-- Notification list -->
            <q-scroll-area v-else style="height: 380px;">
              <q-list separator>
                <q-item
                  v-for="notif in notifications"
                  :key="notif.id"
                  clickable
                  :class="{ 'bg-blue-1': !notif.read_at }"
                  @click="onNotifClick(notif)"
                >
                  <q-item-section side>
                    <q-icon
                      :name="getNotifIcon(notif)"
                      :color="notif.read_at ? 'grey-5' : 'primary'"
                      size="22px"
                    />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2" :class="{ 'text-weight-medium': !notif.read_at }">
                      {{ getNotifMessage(notif) }}
                    </q-item-label>
                    <q-item-label caption>{{ notifTimeAgo(notif.created_at) }}</q-item-label>
                  </q-item-section>
                  <q-item-section v-if="!notif.read_at" side>
                    <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--q-primary);" />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-scroll-area>
          </q-menu>
        </q-btn>

        <!-- Profile dropdown -->
        <q-btn flat round dense class="q-ml-xs">
          <q-avatar size="32px" color="white" text-color="primary" font-size="14px">
            <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Avatar" />
            <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
          </q-avatar>

          <q-menu anchor="bottom right" self="top right" style="min-width: 300px;">
            <q-list>
              <!-- Header: avatar + name + email + profile link -->
              <q-item class="q-py-md">
                <q-item-section avatar>
                  <q-avatar size="40px" color="primary" text-color="white" font-size="18px">
                    <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Avatar" />
                    <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
                  </q-avatar>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-weight-bold">{{ auth.user?.name }}</q-item-label>
                  <q-item-label caption>{{ auth.user?.email }}</q-item-label>
                  <q-item-label>
                    <router-link to="/profile" class="text-primary" style="font-size: 12px; text-decoration: none;">
                      Configuración de perfil
                    </router-link>
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator />

              <!-- Auto-assign toggle (admin/agent only) -->
              <q-item v-if="auth.isAdmin || auth.isAgent">
                <q-item-section>
                  <q-item-label>{{ t('profile.availableForAssignment') }}</q-item-label>
                  <q-item-label caption>{{ t('profile.manuallyControlled') }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-toggle
                    v-model="isAvailableForAssignment"
                    color="primary"
                    @update:model-value="toggleAvailability"
                  />
                </q-item-section>
              </q-item>

              <q-separator v-if="auth.isAdmin || auth.isAgent" />

              <!-- Appearance: auto / light / dark -->
              <q-item>
                <q-item-section avatar>
                  <q-icon :name="themeMode === 'dark' ? 'dark_mode' : themeMode === 'light' ? 'light_mode' : 'brightness_auto'" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ t('profile.appearance') }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-btn-toggle
                    :model-value="themeMode"
                    @update:model-value="applyTheme($event)"
                    dense flat no-caps
                    toggle-color="primary"
                    :options="[
                      { icon: 'brightness_auto', value: 'auto', slot: 'auto' },
                      { icon: 'light_mode', value: 'light', slot: 'light' },
                      { icon: 'dark_mode', value: 'dark', slot: 'dark' },
                    ]"
                    size="sm"
                  >
                    <template v-slot:auto><q-tooltip>Sistema</q-tooltip></template>
                    <template v-slot:light><q-tooltip>Claro</q-tooltip></template>
                    <template v-slot:dark><q-tooltip>Oscuro</q-tooltip></template>
                  </q-btn-toggle>
                </q-item-section>
              </q-item>

              <!-- Sound notifications toggle -->
              <q-item>
                <q-item-section avatar>
                  <q-icon :name="soundEnabled ? 'volume_up' : 'volume_off'" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ t('profile.soundNotifications') }}</q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-toggle
                    :model-value="soundEnabled"
                    color="primary"
                    @update:model-value="toggleSound"
                  />
                </q-item-section>
              </q-item>

              <!-- Restart onboarding tour -->
              <q-item clickable v-close-popup @click="restartTour">
                <q-item-section avatar>
                  <q-icon name="school" />
                </q-item-section>
                <q-item-section>{{ t('profile.restartTour') }}</q-item-section>
              </q-item>

              <q-separator />

              <!-- Logout -->
              <q-item clickable v-close-popup @click="handleLogout">
                <q-item-section avatar>
                  <q-icon name="logout" />
                </q-item-section>
                <q-item-section>{{ t('auth.logout') }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" bordered :width="260" show-if-above>
      <q-list>
        <!-- Tenant branding header -->
        <div class="drawer-brand q-pa-md">
          <div class="row items-center no-wrap q-gutter-sm">
            <div v-if="auth.tenant?.logo_url" class="drawer-brand-logo">
              <img :src="auth.tenant.logo_url" alt="Logo" />
            </div>
            <q-avatar v-else color="primary" text-color="white" size="36px" font-size="16px">
              {{ (auth.tenant?.name || 'A').charAt(0).toUpperCase() }}
            </q-avatar>
            <div class="col ellipsis">
              <div class="text-subtitle2 text-weight-bold ellipsis">
                {{ auth.tenant?.name || 'AutoService' }}
              </div>
              <div v-if="auth.tenant?.slug" class="text-caption text-grey ellipsis">
                {{ auth.tenant.slug }}.autoservice.test
              </div>
            </div>
          </div>
        </div>
        <q-separator />

        <q-item
          v-for="item in menuItems"
          :key="item.to"
          :to="item.to"
          clickable
          v-ripple
          active-class="text-primary bg-blue-1"
        >
          <q-item-section avatar>
            <q-icon :name="item.icon" />
          </q-item-section>
          <q-item-section>{{ t(item.label) }}</q-item-section>
          <q-item-section v-if="(item as any).badge && pendingApprovalsCount > 0" side>
            <q-badge color="red" :label="pendingApprovalsCount" />
          </q-item-section>
        </q-item>

        <template v-if="auth.isAgent && !auth.isAdmin">
          <q-separator class="q-my-sm" />
          <q-item
            to="/settings/canned-responses"
            clickable
            v-ripple
            active-class="text-primary bg-blue-1"
          >
            <q-item-section avatar>
              <q-icon name="quickreply" />
            </q-item-section>
            <q-item-section>{{ t('nav.cannedResponses') }}</q-item-section>
          </q-item>
        </template>

        <template v-if="auth.isAdmin">
          <q-separator class="q-my-sm" />
          <q-item-label header>Admin</q-item-label>
          <q-item
            v-for="item in adminItems"
            :key="item.to"
            :to="item.to"
            clickable
            v-ripple
            active-class="text-primary bg-blue-1"
          >
            <q-item-section avatar>
              <q-icon :name="item.icon" />
            </q-item-section>
            <q-item-section>{{ t(item.label) }}</q-item-section>
          </q-item>
        </template>
      </q-list>
    </q-drawer>

    <RecentActivitiesDrawer v-model="showActivities" />

    <q-page-container>
      <router-view :key="$route.path" />
    </q-page-container>
  </q-layout>
</template>

<style scoped>
.drawer-brand {
  min-height: 56px;
}
.drawer-brand-logo {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.drawer-brand-logo img {
  max-width: 36px;
  max-height: 36px;
  object-fit: contain;
  border-radius: 6px;
}

/* Global search bar */
.header-search {
  max-width: 380px;
  min-width: 200px;
}
.header-search :deep(.q-field__control) {
  height: 36px;
  min-height: 36px;
}
.header-search :deep(.q-field__marginal) {
  height: 36px;
}
.search-shortcut {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border: 1px solid #d0d0d0;
  border-radius: 4px;
  font-size: 11px;
  color: #999;
  font-family: monospace;
}

/* Create button */
.create-btn {
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 4px;
}
.create-btn:hover {
  background: rgba(255, 255, 255, 0.15);
}
</style>
