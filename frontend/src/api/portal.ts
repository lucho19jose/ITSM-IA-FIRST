import { get, post } from '@/utils/api'
import type { User, KbCategory, KbArticle, ServiceCatalogItem, PaginatedResponse, TenantSettings } from '@/types'

export interface PortalTenantInfo {
  id: number
  name: string
  slug: string
  logo_url: string | null
  favicon_url: string | null
  settings: TenantSettings | null
}

interface AuthResponse {
  user: User
  token: string
}

export function getPortalTenantInfo(slug: string) {
  return get<{ data: PortalTenantInfo }>(`portal/${slug}/info`)
}

export function portalLogin(slug: string, data: { email: string; password: string }) {
  return post<AuthResponse>(`portal/${slug}/login`, data)
}

export function portalRegister(slug: string, data: { name: string; email: string; password: string; password_confirmation: string }) {
  return post<AuthResponse>(`portal/${slug}/register`, data)
}

export function getPortalKbCategories(slug: string) {
  return get<{ data: KbCategory[] }>(`portal/${slug}/kb/categories`)
}

export function getPortalKbArticles(slug: string, params?: Record<string, any>) {
  return get<PaginatedResponse<KbArticle>>(`portal/${slug}/kb/articles`, { params })
}

export function getPortalKbArticle(slug: string, id: number) {
  return get<{ data: KbArticle }>(`portal/${slug}/kb/articles/${id}`)
}

export function getPortalCatalog(slug: string) {
  return get<{ data: ServiceCatalogItem[] }>(`portal/${slug}/catalog`)
}
