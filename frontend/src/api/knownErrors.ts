import { get, post, put, del } from '@/utils/api'
import type { KnownError, PaginatedResponse } from '@/types'

export function getKnownErrors(params?: Record<string, any>) {
  return get<PaginatedResponse<KnownError>>('known-errors', { params })
}

export function getKnownError(id: number) {
  return get<{ data: KnownError }>(`known-errors/${id}`)
}

export function createKnownError(data: Partial<KnownError>) {
  return post<{ data: KnownError }>('known-errors', data)
}

export function updateKnownError(id: number, data: Partial<KnownError>) {
  return put<{ data: KnownError }>(`known-errors/${id}`, data)
}

export function deleteKnownError(id: number) {
  return del(`known-errors/${id}`)
}

export function searchKnownErrors(q: string, params?: Record<string, any>) {
  return get<PaginatedResponse<KnownError>>('known-errors/search', { params: { q, ...params } })
}
