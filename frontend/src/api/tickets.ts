import { get, post, put, patch, del } from '@/utils/api'
import api from '@/utils/api'
import type { Ticket, TicketComment, PaginatedResponse } from '@/types'

export function getTickets(params?: Record<string, any>) {
  return get<PaginatedResponse<Ticket>>('tickets', { params })
}

export function getTicket(id: number) {
  return get<{ data: Ticket }>(`tickets/${id}`)
}

export function createTicket(data: Partial<Ticket>) {
  return post<{ data: Ticket }>('tickets', data)
}

export function updateTicket(id: number, data: Partial<Ticket>) {
  return put<{ data: Ticket }>(`tickets/${id}`, data)
}

export function deleteTicket(id: number) {
  return del(`tickets/${id}`)
}

export function assignTicket(id: number, userId: number) {
  return post<{ data: Ticket }>(`tickets/${id}/assign`, { assigned_to: userId })
}

export function addComment(ticketId: number, data: { body: string; is_internal?: boolean }) {
  return post<{ data: TicketComment }>(`tickets/${ticketId}/comments`, data)
}

export function classifyTicket(id: number) {
  return post<{ data: any }>(`tickets/${id}/classify`)
}

export function suggestResponse(id: number) {
  return post<{ data: any }>(`tickets/${id}/suggest-response`)
}

export function uploadTicketAttachments(ticketId: number, files: File[], commentId?: number) {
  const formData = new FormData()
  files.forEach(f => formData.append('files[]', f))
  if (commentId) formData.append('comment_id', String(commentId))
  return post<{ data: any[] }>(`tickets/${ticketId}/attachments`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function deleteTicketAttachment(ticketId: number, attachmentId: number) {
  return del(`tickets/${ticketId}/attachments/${attachmentId}`)
}

export function getTicketTags() {
  return get<{ data: string[] }>('tickets/tags/list')
}


export function improveText(text: string) {
  return post<{ data: { improved_text: string; model: string; processing_time_ms: number } }>('ai/improve-text', { text })
}

export function quickUpdateTicket(id: number, data: { status?: string; priority?: string; assigned_to?: number | null }) {
  return patch<{ data: Ticket }>(`tickets/${id}/quick-update`, data)
}

export function bulkUpdateTickets(data: { ticket_ids: number[]; status?: string; priority?: string; assigned_to?: number | null }) {
  return put<{ message: string; count: number }>('tickets/bulk-update', data)
}

export function mergeTicket(targetId: number, sourceTicketId: number) {
  return post<{ data: Ticket; message: string }>(`tickets/${targetId}/merge`, { source_ticket_id: sourceTicketId })
}

export function toggleSpam(ticketId: number) {
  return post<{ data: Ticket; message: string }>(`tickets/${ticketId}/spam`)
}

export function toggleFavorite(ticketId: number) {
  return post<{ is_favorite: boolean; message: string }>(`tickets/${ticketId}/favorite`)
}

export function shareTicket(ticketId: number, data: { email: string; message?: string }) {
  return post<{ message: string }>(`tickets/${ticketId}/share`, data)
}

export async function exportTickets(data: {
  fields: string[]
  format?: string
  filter_field?: string
  filter_period?: string
  status?: string
  priority?: string
  search?: string
}) {
  const response = await api.post('tickets/export', data, { responseType: 'blob' })
  return response
}
