import { get, post } from '@/utils/api'
import type { Approval } from '@/types'

export function getApprovals(params?: Record<string, string>) {
  return get<{ data: Approval[]; meta: any }>('approvals', { params })
}

export function getApproval(id: number) {
  return get<{ data: Approval }>(`approvals/${id}`)
}

export function getMyPendingApprovals() {
  return get<{ data: Approval[] }>('approvals/my-pending')
}

export function approveApproval(id: number, comment?: string) {
  return post<{ data: Approval; message: string }>(`approvals/${id}/approve`, { comment })
}

export function rejectApproval(id: number, comment: string) {
  return post<{ data: Approval; message: string }>(`approvals/${id}/reject`, { comment })
}
