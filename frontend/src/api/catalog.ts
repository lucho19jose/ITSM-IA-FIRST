import { get, post, put, del } from '@/utils/api'
import type { ServiceCatalogItem } from '@/types'

export function getCatalogItems() {
  return get<{ data: ServiceCatalogItem[] }>('catalog')
}

export function createCatalogItem(data: Partial<ServiceCatalogItem>) {
  return post<{ data: ServiceCatalogItem }>('catalog', data)
}

export function updateCatalogItem(id: number, data: Partial<ServiceCatalogItem>) {
  return put<{ data: ServiceCatalogItem }>(`catalog/${id}`, data)
}

export function deleteCatalogItem(id: number) {
  return del(`catalog/${id}`)
}

export function requestCatalogItem(id: number, data: { description?: string; form_data?: Record<string, any> }) {
  return post(`catalog/${id}/request`, data)
}
