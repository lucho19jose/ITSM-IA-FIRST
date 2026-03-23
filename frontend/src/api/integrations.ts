import { get, post, put, del } from '@/utils/api'
import type { Integration } from '@/types'

export function getIntegrations() {
  return get<{ data: Integration[] }>('integrations')
}

export function getIntegration(id: number) {
  return get<{ data: Integration }>(`integrations/${id}`)
}

export function createIntegration(data: Partial<Integration>) {
  return post<{ data: Integration; message: string }>('integrations', data)
}

export function updateIntegration(id: number, data: Partial<Integration>) {
  return put<{ data: Integration; message: string }>(`integrations/${id}`, data)
}

export function deleteIntegration(id: number) {
  return del<{ message: string }>(`integrations/${id}`)
}

export function testIntegration(id: number) {
  return post<{ success: boolean; message: string }>(`integrations/${id}/test`)
}
