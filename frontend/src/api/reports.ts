import { get, post, put, del } from '@/utils/api'
import type { SavedReport, ReportConfig, ReportResult } from '@/types'

export function getReports() {
  return get<{ data: SavedReport[] }>('reports')
}

export function getReport(id: number) {
  return get<{ data: SavedReport }>(`reports/${id}`)
}

export function createReport(data: Partial<SavedReport>) {
  return post<{ data: SavedReport }>('reports', data)
}

export function updateReport(id: number, data: Partial<SavedReport>) {
  return put<{ data: SavedReport }>(`reports/${id}`, data)
}

export function deleteReport(id: number) {
  return del(`reports/${id}`)
}

export function executeReport(id: number) {
  return post<{ data: ReportResult }>(`reports/${id}/execute`)
}

export function previewReport(config: ReportConfig) {
  return post<{ data: ReportResult }>('reports/preview', config)
}

export function getReportTemplates() {
  return get<{ data: Array<{ name: string; description: string; report_type: string; config: ReportConfig }> }>('reports/templates')
}

export function getAvailableFields(entity: string) {
  return get<{
    data: {
      filters: Array<{ field: string; label: string; type: string; operators: string[]; options?: string[] }>
      metrics: Array<{ key: string; label: string; description: string }>
      groupings: Array<{ key: string; label: string }>
    }
  }>(`reports/available-fields?entity=${entity}`)
}

export function exportReportUrl(id: number): string {
  const token = localStorage.getItem('token')
  return `/api/v1/reports/${id}/export?token=${token}`
}
