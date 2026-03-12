import { get, post, put, del } from '@/utils/api'
import type { Category } from '@/types'

export function getCategories() {
  return get<{ data: Category[] }>('categories')
}

export function createCategory(data: Partial<Category>) {
  return post<{ data: Category }>('categories', data)
}

export function updateCategory(id: number, data: Partial<Category>) {
  return put<{ data: Category }>(`categories/${id}`, data)
}

export function deleteCategory(id: number) {
  return del(`categories/${id}`)
}
