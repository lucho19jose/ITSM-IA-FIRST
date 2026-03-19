import { get, post, del } from '@/utils/api'

export function getTicketAssociations(ticketId: number) {
  return get(`tickets/${ticketId}/associations`)
}

export function createTicketAssociation(ticketId: number, data: { related_ticket_id: number; type: string }) {
  return post(`tickets/${ticketId}/associations`, data)
}

export function deleteTicketAssociation(ticketId: number, associationId: number) {
  return del(`tickets/${ticketId}/associations/${associationId}`)
}
