import { get, post, put, del } from '@/utils/api'
import type { AutomationRule, AutomationLog } from '@/types'

export function getAutomationRules() {
  return get<{ data: AutomationRule[] }>('automation-rules')
}

export function getAutomationRule(id: number) {
  return get<{ data: AutomationRule }>(`automation-rules/${id}`)
}

export function createAutomationRule(data: Partial<AutomationRule>) {
  return post<{ data: AutomationRule; message: string }>('automation-rules', data)
}

export function updateAutomationRule(id: number, data: Partial<AutomationRule>) {
  return put<{ data: AutomationRule; message: string }>(`automation-rules/${id}`, data)
}

export function deleteAutomationRule(id: number) {
  return del<{ message: string }>(`automation-rules/${id}`)
}

export function reorderAutomationRules(rules: { id: number; execution_order: number }[]) {
  return post<{ message: string }>('automation-rules/reorder', { rules })
}

export function toggleAutomationRule(id: number) {
  return post<{ data: AutomationRule; message: string }>(`automation-rules/${id}/toggle`)
}

export function testAutomationRule(ruleId: number, ticketId: number) {
  return post<{ data: AutomationRuleTestResult }>(`automation-rules/${ruleId}/test/${ticketId}`)
}

export function getAutomationRuleLogs(id: number) {
  return get<{ data: AutomationLog[] }>(`automation-rules/${id}/logs`)
}

export function getAvailableFields() {
  return get<{ data: AvailableFieldsResponse }>('automation-rules/available-fields')
}

export function getAutomationTemplates() {
  return get<{ data: AutomationTemplate[] }>('automation-rules/templates')
}

// Types for API responses
export interface AvailableFieldsResponse {
  condition_fields: ConditionFieldMeta[]
  actions: ActionMeta[]
  operators: OperatorMeta[]
  trigger_events: TriggerEventMeta[]
}

export interface ConditionFieldMeta {
  key: string
  label: string
  type: 'text' | 'select' | 'number' | 'boolean'
  options?: { label: string; value: string }[]
}

export interface ActionMeta {
  type: string
  label: string
  description: string
  config_schema: ActionConfigField[]
}

export interface ActionConfigField {
  key: string
  label: string
  type: string
  options?: { label: string; value: string }[]
}

export interface OperatorMeta {
  value: string
  label: string
  types: string[]
}

export interface TriggerEventMeta {
  value: string
  label: string
}

export interface AutomationTemplate {
  name: string
  description: string
  trigger_event: string
  conditions: any[][]
  actions: any[]
  stop_on_match: boolean
}

export interface AutomationRuleTestResult {
  conditions_matched: boolean
  condition_details: {
    group_index: number
    conditions: {
      field: string
      operator: string
      expected_value: any
      actual_value: any
      matched: boolean
    }[]
    group_matched: boolean
  }[]
  actions_preview: {
    type: string
    description: string
    config: any
  }[]
}
