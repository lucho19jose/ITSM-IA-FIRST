import { get, post, put, del } from '@/utils/api'
import type { ApprovalWorkflow } from '@/types'

export function getApprovalWorkflows() {
  return get<{ data: ApprovalWorkflow[] }>('approval-workflows')
}

export function getApprovalWorkflow(id: number) {
  return get<{ data: ApprovalWorkflow }>(`approval-workflows/${id}`)
}

export function createApprovalWorkflow(data: Partial<ApprovalWorkflow>) {
  return post<{ data: ApprovalWorkflow }>('approval-workflows', data)
}

export function updateApprovalWorkflow(id: number, data: Partial<ApprovalWorkflow>) {
  return put<{ data: ApprovalWorkflow }>(`approval-workflows/${id}`, data)
}

export function deleteApprovalWorkflow(id: number) {
  return del(`approval-workflows/${id}`)
}
