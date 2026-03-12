import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { get } from '@/utils/api'
import { getSubdomain } from '@/utils/tenant'

interface TenantInfo {
  id: number
  name: string
  slug: string
  plan: string
}

export const useTenantStore = defineStore('tenant', () => {
  const tenantInfo = ref<TenantInfo | null>(null)
  const loading = ref(false)
  const subdomain = ref<string | null>(getSubdomain())

  const isSubdomainAccess = computed(() => subdomain.value !== null)
  const tenantName = computed(() => tenantInfo.value?.name || null)

  async function fetchTenantInfo() {
    if (!subdomain.value) return

    loading.value = true
    try {
      const res = await get<{ data: TenantInfo | null }>('tenant-info')
      tenantInfo.value = res.data
    } catch {
      tenantInfo.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    tenantInfo, loading, subdomain,
    isSubdomainAccess, tenantName,
    fetchTenantInfo,
  }
})
