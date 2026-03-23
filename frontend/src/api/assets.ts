import { get, post, put, del } from '@/utils/api'
import api from '@/utils/api'
import type { Asset, AssetRelationship, AssetLog, PaginatedResponse } from '@/types'

export function getAssets(params?: Record<string, any>) {
  return get<PaginatedResponse<Asset>>('assets', { params })
}

export function getAsset(id: number) {
  return get<{ data: Asset }>(`assets/${id}`)
}

export function createAsset(data: Partial<Asset>) {
  return post<{ data: Asset }>('assets', data)
}

export function updateAsset(id: number, data: Partial<Asset>) {
  return put<{ data: Asset }>(`assets/${id}`, data)
}

export function deleteAsset(id: number) {
  return del(`assets/${id}`)
}

export function assignAsset(id: number, userId: number) {
  return post<{ data: Asset; message: string }>(`assets/${id}/assign`, { user_id: userId })
}

export function unassignAsset(id: number) {
  return post<{ data: Asset; message: string }>(`assets/${id}/unassign`)
}

export function linkTicketToAsset(assetId: number, ticketId: number) {
  return post<{ message: string }>(`assets/${assetId}/link-ticket/${ticketId}`)
}

export function unlinkTicketFromAsset(assetId: number, ticketId: number) {
  return del<{ message: string }>(`assets/${assetId}/unlink-ticket/${ticketId}`)
}

export function getAssetRelationships(id: number) {
  return get<{ data: AssetRelationship[] }>(`assets/${id}/relationships`)
}

export function addAssetRelationship(id: number, data: { target_asset_id: number; relationship_type: string }) {
  return post<{ data: any; message: string }>(`assets/${id}/relationships`, data)
}

export function removeAssetRelationship(assetId: number, relationshipId: number) {
  return del<{ message: string }>(`assets/${assetId}/relationships/${relationshipId}`)
}

export function getAssetTimeline(id: number) {
  return get<{ data: AssetLog[] }>(`assets/${id}/timeline`)
}

export function getAssetDashboard() {
  return get<{ data: { total: number; by_status: Record<string, number>; by_type: Record<string, number>; expiring_warranties: number; active: number; maintenance: number; retired: number } }>('assets/dashboard/stats')
}

export function getNextAssetTag() {
  return get<{ data: { asset_tag: string } }>('assets/next-tag')
}

export async function exportAssets(data: { status?: string; asset_type_id?: number }) {
  const response = await api.post('assets/export', data, { responseType: 'blob' })
  return response
}
