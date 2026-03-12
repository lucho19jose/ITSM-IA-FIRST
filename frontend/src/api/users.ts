import { get, post, put, del } from '@/utils/api'
import type { User } from '@/types'

export function getUsers() {
  return get<{ data: User[] }>('users')
}

export function createUser(data: { name: string; email: string; password: string; role: string }) {
  return post<{ data: User }>('users', data)
}

export function updateUser(id: number, data: Partial<User & { password?: string }>) {
  return put<{ data: User }>(`users/${id}`, data)
}

export function deleteUser(id: number) {
  return del(`users/${id}`)
}

export function getAgents() {
  return get<{ data: User[] }>('users/agents/list')
}
