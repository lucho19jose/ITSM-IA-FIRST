import { get, post, put, del } from '@/utils/api'
import type { Department } from '@/types'

export function getDepartments() {
  return get<{ data: Department[] }>('departments')
}

export function createDepartment(data: Partial<Department>) {
  return post<{ data: Department }>('departments', data)
}

export function updateDepartment(id: number, data: Partial<Department>) {
  return put<{ data: Department }>(`departments/${id}`, data)
}

export function deleteDepartment(id: number) {
  return del(`departments/${id}`)
}
