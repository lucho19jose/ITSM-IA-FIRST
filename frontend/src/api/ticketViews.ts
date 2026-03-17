import { get, post, put, del } from '@/utils/api'

export interface TicketViewData {
  id?: number
  name: string
  icon: string
  filters: Record<string, any>
  columns?: string[] | null
  is_default?: boolean
  is_shared?: boolean
  sort_order?: number
  user_id?: number
  created_at?: string
  updated_at?: string
}

export function getTicketViews() {
  return get<{ data: TicketViewData[] }>('ticket-views')
}

export function createTicketView(data: Omit<TicketViewData, 'id' | 'user_id' | 'created_at' | 'updated_at'>) {
  return post<{ data: TicketViewData }>('ticket-views', data)
}

export function updateTicketView(id: number, data: Partial<TicketViewData>) {
  return put<{ data: TicketViewData }>(`ticket-views/${id}`, data)
}

export function deleteTicketView(id: number) {
  return del(`ticket-views/${id}`)
}
