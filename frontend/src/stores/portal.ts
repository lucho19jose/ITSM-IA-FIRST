import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getPortalTenantInfo, portalLogin, portalRegister, type PortalTenantInfo } from '@/api/portal'
import { get, post } from '@/utils/api'
import type { User } from '@/types'

export const usePortalStore = defineStore('portal', () => {
  const tenant = ref<PortalTenantInfo | null>(null)
  const user = ref<User | null>(null)
  const loading = ref(false)
  const tenantLoading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const tenantSlug = computed(() => tenant.value?.slug || null)

  async function fetchTenant(slug: string) {
    tenantLoading.value = true
    try {
      const res = await getPortalTenantInfo(slug)
      tenant.value = res.data
    } catch {
      tenant.value = null
    } finally {
      tenantLoading.value = false
    }
  }

  async function login(slug: string, email: string, password: string) {
    loading.value = true
    try {
      const res = await portalLogin(slug, { email, password })
      localStorage.setItem('token', res.token)
      localStorage.setItem('portal_tenant_slug', slug)
      user.value = res.user
      return res
    } finally {
      loading.value = false
    }
  }

  async function register(slug: string, data: { name: string; email: string; password: string; password_confirmation: string }) {
    loading.value = true
    try {
      const res = await portalRegister(slug, data)
      localStorage.setItem('token', res.token)
      localStorage.setItem('portal_tenant_slug', slug)
      user.value = res.user
      return res
    } finally {
      loading.value = false
    }
  }

  async function restoreSession() {
    const token = localStorage.getItem('token')
    if (!token) return

    try {
      const res = await get<{ user: User }>('auth/me')
      user.value = res.user
    } catch {
      localStorage.removeItem('token')
      localStorage.removeItem('portal_tenant_slug')
      user.value = null
    }
  }

  function logout() {
    post('auth/logout').catch(() => {})
    localStorage.removeItem('token')
    localStorage.removeItem('portal_tenant_slug')
    user.value = null
  }

  return {
    tenant, user, loading, tenantLoading,
    isAuthenticated, tenantSlug,
    fetchTenant, login, register, restoreSession, logout,
  }
})
