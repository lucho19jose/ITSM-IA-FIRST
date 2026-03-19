import { get, post, put, del } from '@/utils/api'

export function getTimeEntries(ticketId: number) {
  return get(`tickets/${ticketId}/time-entries`)
}

export function addTimeEntry(ticketId: number, data: { hours: number; note?: string; executed_at: string; billable?: boolean }) {
  return post(`tickets/${ticketId}/time-entries`, data)
}

export function updateTimeEntry(ticketId: number, entryId: number, data: { hours?: number; note?: string; executed_at?: string; billable?: boolean }) {
  return put(`tickets/${ticketId}/time-entries/${entryId}`, data)
}

export function deleteTimeEntry(ticketId: number, entryId: number) {
  return del(`tickets/${ticketId}/time-entries/${entryId}`)
}
