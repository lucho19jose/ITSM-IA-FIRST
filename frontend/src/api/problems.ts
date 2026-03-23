import { get, post, put, del } from '@/utils/api'
import type { Problem, PaginatedResponse } from '@/types'

export function getProblems(params?: Record<string, any>) {
  return get<PaginatedResponse<Problem>>('problems', { params })
}

export function getProblem(id: number) {
  return get<{ data: Problem }>(`problems/${id}`)
}

export function createProblem(data: Partial<Problem> & { ticket_ids?: number[] }) {
  return post<{ data: Problem }>('problems', data)
}

export function updateProblem(id: number, data: Partial<Problem>) {
  return put<{ data: Problem }>(`problems/${id}`, data)
}

export function deleteProblem(id: number) {
  return del(`problems/${id}`)
}

export function linkTickets(problemId: number, ticketIds: number[]) {
  return post<{ data: Problem; message: string }>(`problems/${problemId}/link-tickets`, { ticket_ids: ticketIds })
}

export function unlinkTicket(problemId: number, ticketId: number) {
  return del<{ data: Problem; message: string }>(`problems/${problemId}/unlink-ticket/${ticketId}`)
}

export function promoteToKnownError(problemId: number) {
  return post<{ data: Problem; known_error: any; message: string }>(`problems/${problemId}/promote-known-error`)
}

export function updateRootCause(problemId: number, data: { root_cause: string; workaround?: string }) {
  return put<{ data: Problem; message: string }>(`problems/${problemId}/root-cause`, data)
}

export function resolveProblem(problemId: number, resolution: string) {
  return post<{ data: Problem; message: string }>(`problems/${problemId}/resolve`, { resolution })
}

export function closeProblem(problemId: number) {
  return post<{ data: Problem; message: string }>(`problems/${problemId}/close`)
}
