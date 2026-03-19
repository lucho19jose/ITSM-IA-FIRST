import { get } from '@/utils/api'

export function getRecentActivities(params?: Record<string, any>) {
  return get('activity-logs', { params })
}
