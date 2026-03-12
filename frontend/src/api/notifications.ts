import { get, put } from '@/utils/api'

export function getNotifications(params?: Record<string, any>) {
  return get('notifications', { params })
}

export function markNotificationRead(id: string) {
  return put(`notifications/${id}/read`)
}

export function getUnreadCount() {
  return get<{ data: { count: number } }>('notifications/unread-count')
}
