import { get } from '@/utils/api'

export interface DashboardSummary {
  total_tickets: number
  open_tickets: number
  in_progress_tickets: number
  pending_tickets: number
  resolved_today: number
  overdue_tickets: number
  due_today: number
  unassigned_tickets: number
  avg_response_time: number
  sla_compliance: number
  csat_average: number | null
  csat_response_rate: number
  csat_total_surveys: number
}

export function getDashboardSummary() {
  return get<{ data: DashboardSummary }>('dashboard/summary')
}

export function getTicketsByStatus() {
  return get<{ data: Record<string, number> }>('dashboard/tickets-by-status')
}

export function getTicketsByPriority() {
  return get<{ data: Record<string, number> }>('dashboard/tickets-by-priority')
}

export function getTrends() {
  return get<{ data: Array<{ date: string; created: number; resolved: number }> }>('dashboard/trends')
}

export function getAgentPerformance() {
  return get<{ data: Array<{ agent_name: string; total_tickets: number; resolved_tickets: number; avg_resolution_minutes: number }> }>('dashboard/agent-performance')
}
