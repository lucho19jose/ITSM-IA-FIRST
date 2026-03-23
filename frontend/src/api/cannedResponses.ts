import { get, post, put, del } from '@/utils/api'
import type { CannedResponse } from '@/types'

export function getCannedResponses(params?: { category?: string; visibility?: string; search?: string }) {
  return get<{ data: CannedResponse[] }>('canned-responses', { params })
}

export function getCannedResponse(id: number) {
  return get<{ data: CannedResponse }>(`canned-responses/${id}`)
}

export function createCannedResponse(data: {
  title: string
  content: string
  category?: string
  visibility?: string
  shortcut?: string
}) {
  return post<{ data: CannedResponse }>('canned-responses', data)
}

export function updateCannedResponse(id: number, data: Partial<{
  title: string
  content: string
  category: string | null
  visibility: string
  shortcut: string | null
}>) {
  return put<{ data: CannedResponse }>(`canned-responses/${id}`, data)
}

export function deleteCannedResponse(id: number) {
  return del(`canned-responses/${id}`)
}

export function searchCannedResponses(q: string) {
  return get<{ data: CannedResponseSearchResult[] }>('canned-responses/search', { params: { q } })
}

export function useCannedResponse(id: number) {
  return post(`canned-responses/${id}/use`)
}

export interface CannedResponseSearchResult {
  id: number
  title: string
  shortcut: string | null
  content: string
  category: string | null
  visibility: string
  content_preview: string
}
