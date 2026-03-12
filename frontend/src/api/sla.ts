import { get, post, put, del } from '@/utils/api'
import type { SlaPolicy } from '@/types'

export function getSlaPolicies() {
  return get<{ data: SlaPolicy[] }>('sla-policies')
}

export function createSlaPolicy(data: Partial<SlaPolicy>) {
  return post<{ data: SlaPolicy }>('sla-policies', data)
}

export function updateSlaPolicy(id: number, data: Partial<SlaPolicy>) {
  return put<{ data: SlaPolicy }>(`sla-policies/${id}`, data)
}

export function deleteSlaPolicy(id: number) {
  return del(`sla-policies/${id}`)
}
