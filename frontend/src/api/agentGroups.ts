import { get, post, put, del } from '@/utils/api'

export function getAgentGroups() {
  return get('agent-groups')
}

export function getAgentGroup(id: number) {
  return get(`agent-groups/${id}`)
}

export function createAgentGroup(data: { name: string; description?: string; member_ids?: number[] }) {
  return post('agent-groups', data)
}

export function updateAgentGroup(id: number, data: { name?: string; description?: string; is_active?: boolean; member_ids?: number[] }) {
  return put(`agent-groups/${id}`, data)
}

export function deleteAgentGroup(id: number) {
  return del(`agent-groups/${id}`)
}
