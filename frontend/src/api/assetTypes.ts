import { get, post, put, del } from '@/utils/api'
import type { AssetType } from '@/types'

export function getAssetTypes() {
  return get<{ data: AssetType[] }>('asset-types')
}

export function getAssetType(id: number) {
  return get<{ data: AssetType }>(`asset-types/${id}`)
}

export function createAssetType(data: Partial<AssetType>) {
  return post<{ data: AssetType }>('asset-types', data)
}

export function updateAssetType(id: number, data: Partial<AssetType>) {
  return put<{ data: AssetType }>(`asset-types/${id}`, data)
}

export function deleteAssetType(id: number) {
  return del(`asset-types/${id}`)
}
