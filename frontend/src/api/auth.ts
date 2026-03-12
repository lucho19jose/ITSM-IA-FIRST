import { post, get } from '@/utils/api'
import type { User } from '@/types'

interface AuthResponse {
  user: User
  token: string
}

interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  company_name: string
  ruc?: string
}

interface LoginData {
  email: string
  password: string
}

export function register(data: RegisterData) {
  return post<AuthResponse>('auth/register', data)
}

export function login(data: LoginData) {
  return post<AuthResponse>('auth/login', data)
}

export function logout() {
  return post('auth/logout')
}

export function getMe() {
  return get<{ user: User }>('auth/me')
}
