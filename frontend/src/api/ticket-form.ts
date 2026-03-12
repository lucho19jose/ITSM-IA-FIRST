import { get, put, post, del } from '@/utils/api'
import type { TicketFormField } from '@/types'

export function getTicketFormFields() {
  return get<{ data: TicketFormField[] }>('ticket-form-fields')
}

export function bulkUpdateFormFields(fields: Partial<TicketFormField>[]) {
  return put<{ data: TicketFormField[] }>('ticket-form-fields/bulk', { fields })
}

export function createCustomField(data: Partial<TicketFormField>) {
  return post<{ data: TicketFormField }>('ticket-form-fields/custom', data)
}

export function deleteCustomField(id: number) {
  return del<{ message: string }>(`ticket-form-fields/${id}`)
}
