import { get, post, put, del } from '@/utils/api'

export function getScenarios() {
  return get('scenarios')
}

export function createScenario(data: { name: string; description?: string; actions: { field: string; value: any }[] }) {
  return post('scenarios', data)
}

export function updateScenario(id: number, data: { name?: string; description?: string; actions?: { field: string; value: any }[]; is_active?: boolean }) {
  return put(`scenarios/${id}`, data)
}

export function deleteScenario(id: number) {
  return del(`scenarios/${id}`)
}

export function executeScenario(ticketId: number, scenarioId: number) {
  return post(`tickets/${ticketId}/run-scenario`, { scenario_id: scenarioId })
}
