import { get, post, put, del } from '@/utils/api'
import type { ChangeRequest, PaginatedResponse } from '@/types'

export function getChangeRequests(params?: Record<string, any>) {
  return get<PaginatedResponse<ChangeRequest>>('change-requests', { params })
}

export function getChangeRequest(id: number) {
  return get<{ data: ChangeRequest }>(`change-requests/${id}`)
}

export function createChangeRequest(data: Partial<ChangeRequest>) {
  return post<{ data: ChangeRequest }>('change-requests', data)
}

export function updateChangeRequest(id: number, data: Partial<ChangeRequest>) {
  return put<{ data: ChangeRequest }>(`change-requests/${id}`, data)
}

export function deleteChangeRequest(id: number) {
  return del(`change-requests/${id}`)
}

export function submitChangeRequest(id: number) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/submit`)
}

export function assessRisk(id: number) {
  return post<{ data: ChangeRequest; assessment: any; model: string; processing_time_ms: number }>(`change-requests/${id}/assess-risk`)
}

export function requestCabReview(id: number, approverIds: number[]) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/request-cab-review`, { approver_ids: approverIds })
}

export function approveCab(id: number, comment?: string) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/approve-cab`, { comment })
}

export function rejectCab(id: number, comment: string) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/reject-cab`, { comment })
}

export function scheduleChange(id: number, scheduledStart: string, scheduledEnd: string) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/schedule`, {
    scheduled_start: scheduledStart,
    scheduled_end: scheduledEnd,
  })
}

export function startImplementation(id: number) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/start-implementation`)
}

export function completeImplementation(id: number) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/complete-implementation`)
}

export function closeReview(id: number, reviewNotes: string) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/close-review`, { review_notes: reviewNotes })
}

export function linkTickets(id: number, ticketIds: number[], relationshipType?: string) {
  return post<{ data: ChangeRequest }>(`change-requests/${id}/link-tickets`, {
    ticket_ids: ticketIds,
    relationship_type: relationshipType || 'related',
  })
}

export function getChangeCalendar(params?: { from?: string; to?: string }) {
  return get<{ data: any[] }>('change-requests/calendar', { params })
}
