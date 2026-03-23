import { get, post } from '@/utils/api'
import type { SatisfactionSurvey, PaginatedResponse } from '@/types'

export interface SurveyByToken {
  id: number
  rating: number | null
  comment: string | null
  responded_at: string | null
  ticket: {
    id: number
    ticket_number: string
    title: string
    agent_name: string | null
  } | null
  user_name: string | null
}

export interface SurveyStats {
  average_rating: number | null
  total_surveys: number
  responded_surveys: number
  response_rate: number
  rating_distribution: Record<number, number>
  trend: Array<{ week: string; avg_rating: number; count: number }>
}

export function getSurveyByToken(token: string) {
  return get<{ data: SurveyByToken }>(`survey/${token}`)
}

export function submitSurvey(token: string, data: { rating: number; comment?: string }) {
  return post<{ message: string; data: { rating: number; comment: string | null; responded_at: string } }>(`survey/${token}`, data)
}

export function getSurveyStats() {
  return get<{ data: SurveyStats }>('satisfaction-surveys/stats')
}

export function getSurveys(params?: Record<string, any>) {
  return get<PaginatedResponse<SatisfactionSurvey>>('satisfaction-surveys', { params })
}
