import { get, post, put, del } from '@/utils/api'
import type { KbCategory, KbArticle, PaginatedResponse } from '@/types'

export function getKbCategories() {
  return get<{ data: KbCategory[] }>('kb/categories')
}

export function createKbCategory(data: Partial<KbCategory>) {
  return post<{ data: KbCategory }>('kb/categories', data)
}

export function getKbArticles(params?: Record<string, any>) {
  return get<PaginatedResponse<KbArticle>>('kb/articles', { params })
}

export function getKbArticle(id: number) {
  return get<{ data: KbArticle }>(`kb/articles/${id}`)
}

export function createKbArticle(data: Partial<KbArticle>) {
  return post<{ data: KbArticle }>('kb/articles', data)
}

export function updateKbArticle(id: number, data: Partial<KbArticle>) {
  return put<{ data: KbArticle }>(`kb/articles/${id}`, data)
}

export function deleteKbArticle(id: number) {
  return del(`kb/articles/${id}`)
}

export function markHelpful(id: number, helpful: boolean) {
  return post(`kb/articles/${id}/helpful`, { helpful })
}
