<script setup lang="ts">
import { watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'
import { applyTenantTheme } from '@/utils/theme'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()

const tenantSlug = route.params.tenantSlug as string

// Load tenant info on mount
onMounted(async () => {
  await portal.fetchTenant(tenantSlug)

  if (!portal.tenant) {
    router.replace('/login')
    return
  }

  // Restore session if token exists
  if (localStorage.getItem('token') && localStorage.getItem('portal_tenant_slug') === tenantSlug) {
    await portal.restoreSession()
  }
})

// Apply tenant theme
watch(
  () => portal.tenant?.settings,
  (settings) => applyTenantTheme(settings),
  { immediate: true, deep: true }
)

// Dynamic favicon
watch(
  () => portal.tenant?.favicon_url,
  (url) => {
    const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (link) link.href = url || '/vite.svg'
  },
  { immediate: true }
)

function handleLogout() {
  portal.logout()
  router.push(`/portal/${tenantSlug}/login`)
}
</script>

<template>
  <q-layout view="hHh lpR fFf">
    <!-- Header -->
    <q-header elevated class="bg-primary">
      <q-toolbar class="portal-toolbar">
        <!-- Tenant branding -->
        <router-link :to="`/portal/${tenantSlug}`" class="portal-brand row items-center no-wrap q-gutter-sm text-white" style="text-decoration: none;">
          <img v-if="portal.tenant?.logo_url" :src="portal.tenant.logo_url" alt="Logo" class="portal-logo" />
          <span class="text-weight-bold text-subtitle1 gt-xs">{{ portal.tenant?.name || 'Portal' }}</span>
        </router-link>

        <q-space />

        <!-- Auth links (not logged in) -->
        <template v-if="!portal.isAuthenticated">
          <router-link :to="`/portal/${tenantSlug}/login`" class="text-white q-mr-md" style="text-decoration: none; font-weight: 500;">
            Iniciar sesion
          </router-link>
          <router-link :to="`/portal/${tenantSlug}/register`" class="text-white" style="text-decoration: none; font-weight: 500;">
            Registrarse
          </router-link>
        </template>

        <!-- User menu (logged in) -->
        <template v-else>
          <q-btn flat round icon="notifications" color="white" class="q-mr-xs">
            <q-badge color="red" floating v-if="false">0</q-badge>
          </q-btn>

          <q-btn flat round dense>
            <q-avatar size="32px" color="white" text-color="primary" font-size="14px">
              <img v-if="portal.user?.avatar_url" :src="portal.user.avatar_url" alt="Avatar" />
              <span v-else>{{ portal.user?.name?.charAt(0)?.toUpperCase() }}</span>
            </q-avatar>

            <q-menu anchor="bottom right" self="top right" style="min-width: 220px;">
              <q-list>
                <q-item class="q-py-md">
                  <q-item-section avatar>
                    <q-avatar size="36px" color="primary" text-color="white" font-size="16px">
                      <img v-if="portal.user?.avatar_url" :src="portal.user.avatar_url" alt="Avatar" />
                      <span v-else>{{ portal.user?.name?.charAt(0)?.toUpperCase() }}</span>
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-weight-bold">{{ portal.user?.name }}</q-item-label>
                    <q-item-label caption>{{ portal.user?.email }}</q-item-label>
                  </q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup :to="`/portal/${tenantSlug}/tickets`">
                  <q-item-section avatar><q-icon name="confirmation_number" /></q-item-section>
                  <q-item-section>Mis Tickets</q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup @click="handleLogout">
                  <q-item-section avatar><q-icon name="logout" /></q-item-section>
                  <q-item-section>Cerrar Sesion</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </template>
      </q-toolbar>
    </q-header>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<style scoped>
.portal-toolbar {
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}
.portal-brand {
  gap: 8px;
}
.portal-logo {
  max-width: 36px;
  max-height: 36px;
  object-fit: contain;
  border-radius: 6px;
}
</style>
