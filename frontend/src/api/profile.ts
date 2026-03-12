import { get, put, post, del } from '@/utils/api'
import type { User } from '@/types'

export function getProfile() {
  return get<{ data: User }>('profile')
}

export function updateProfile(data: Partial<User>) {
  return put<{ data: User; message: string }>('profile', data)
}

export function changePassword(data: { current_password: string; new_password: string; new_password_confirmation: string }) {
  return put<{ message: string }>('profile/password', data)
}

export function uploadAvatar(file: File) {
  const formData = new FormData()
  formData.append('avatar', file)
  return post<{ data: User; message: string }>('profile/avatar', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function deleteAvatar() {
  return del<{ data: User; message: string }>('profile/avatar')
}
