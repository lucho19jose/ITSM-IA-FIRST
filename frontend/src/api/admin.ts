import { get, post, put, del } from '@/utils/api'
import type { Tenant, User } from '@/types'

export interface PlatformStats {
  total_tenants: number
  active_tenants: number
  total_users: number
  total_tickets: number
  tenants_by_plan: Record<string, number>
  recent_tenants: Tenant[]
}

export interface TenantWithStats {
  data: Tenant & { users_count?: number }
  stats: {
    users_count: number
    tickets_count: number
    open_tickets: number
    admin_users: number
    agent_users: number
    end_users: number
  }
}

export function getPlatformStats() {
  return get<{ data: PlatformStats }>('admin/stats')
}

export function getTenants(params?: Record<string, any>) {
  return get<any>('admin/tenants', { params })
}

export function getTenant(id: number) {
  return get<TenantWithStats>(`admin/tenants/${id}`)
}

export function createTenant(data: any) {
  return post<{ data: Tenant }>('admin/tenants', data)
}

export function updateTenant(id: number, data: any) {
  return put<{ data: Tenant }>(`admin/tenants/${id}`, data)
}

export function deleteTenant(id: number) {
  return del(`admin/tenants/${id}`)
}

export function toggleTenantActive(id: number) {
  return post<{ data: Tenant }>(`admin/tenants/${id}/toggle-active`)
}

export function getTenantUsers(id: number) {
  return get<{ data: User[] }>(`admin/tenants/${id}/users`)
}

export function impersonateTenant(id: number) {
  return post<{ user: User; token: string }>(`admin/tenants/${id}/impersonate`)
}
