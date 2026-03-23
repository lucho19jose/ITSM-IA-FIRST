export interface TenantSettings {
  primary_color?: string | null
  secondary_color?: string | null
  accent_color?: string | null
  [key: string]: unknown
}

export interface Tenant {
  id: number
  name: string
  slug: string
  custom_domain: string | null
  logo_path: string | null
  logo_url: string | null
  favicon_path: string | null
  favicon_url: string | null
  ruc: string | null
  plan: string
  settings: TenantSettings | null
  is_active: boolean
  trial_ends_at: string | null
  created_at: string
}

export interface Department {
  id: number
  name: string
  description: string | null
  head_id: number | null
  is_active: boolean
  head?: User
  created_at: string
  updated_at: string
}

export interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'agent' | 'end_user' | 'super_admin'
  is_active: boolean
  is_vip: boolean
  department_id: number | null
  phone: string | null
  location: string | null
  job_title: string | null
  timezone: string
  language: string
  avatar_path: string | null
  avatar_url: string | null
  signature: string | null
  is_available_for_assignment: boolean
  time_format: string
  address: string | null
  work_phone: string | null
  tenant_id: number
  tenant?: Tenant
  department?: Department
  created_at: string
}

export interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  parent_id: number | null
  icon: string | null
  is_active: boolean
  sort_order: number
}

export interface SlaPolicy {
  id: number
  name: string
  priority: 'low' | 'medium' | 'high' | 'urgent'
  response_time: number
  resolution_time: number
  is_active: boolean
}

export type TicketSource = 'portal' | 'email' | 'chatbot' | 'catalog' | 'api' | 'phone'

export interface Ticket {
  id: number
  ticket_number: string
  title: string
  description: string
  type: 'incident' | 'request' | 'problem' | 'change'
  status: 'open' | 'in_progress' | 'pending' | 'resolved' | 'closed'
  priority: 'low' | 'medium' | 'high' | 'urgent'
  source: TicketSource
  category_id: number | null
  department_id: number | null
  subcategory: string | null
  item: string | null
  requester_id: number
  assigned_to: number | null
  category?: Category
  department?: Department
  requester?: User
  assignee?: User
  sla_policy?: SlaPolicy
  comments?: TicketComment[]
  attachments?: TicketAttachment[]
  tags: string[]
  custom_fields: Record<string, unknown>
  impact: string | null
  urgency: string | null
  status_details: string | null
  approval_status: string | null
  association_type: string | null
  major_incident_type: string | null
  contact_number: string | null
  requester_location: string | null
  specific_subject: string | null
  customers_impacted: number | null
  impacted_locations: string[] | null
  planned_effort: string | null
  responded_at: string | null
  resolved_at: string | null
  closed_at: string | null
  due_date: string | null
  planned_start_date: string | null
  planned_end_date: string | null
  response_due_at: string | null
  resolution_due_at: string | null
  agent_group_id: number | null
  asset_id: number | null
  agent_group?: AgentGroup
  asset?: Asset
  satisfaction_rating: number | null
  resolution_notes: string | null
  is_spam: boolean
  time_entries?: TimeEntry[]
  created_at: string
  updated_at: string
}

export interface TimeEntry {
  id: number
  ticket_id: number
  user: {
    id: number
    name: string
    avatar_url: string | null
  }
  hours: number
  note: string | null
  executed_at: string
  billable: boolean
  created_at: string
}

export interface TicketAssociation {
  id: number
  ticket_id: number
  related_ticket_id: number
  type: 'parent' | 'child' | 'related' | 'cause'
  related_ticket: {
    id: number
    ticket_number: string
    title: string
    status: string
    priority: string
  }
  created_at: string
}

export interface TicketComment {
  id: number
  ticket_id: number
  user_id: number
  body: string
  is_internal: boolean
  user?: User
  attachments?: TicketAttachment[]
  created_at: string
}

export interface TicketAttachment {
  id: number
  ticket_id: number
  comment_id: number | null
  user_id: number
  filename: string
  path: string
  mime_type: string
  size: number
  user?: User
  created_at: string
}

export interface KbCategory {
  id: number
  name: string
  slug: string
  description: string | null
  icon: string | null
  sort_order: number
  is_active: boolean
  articles_count?: number
}

export interface KbArticle {
  id: number
  category_id: number
  title: string
  slug: string
  content: string
  excerpt: string | null
  status: 'draft' | 'published' | 'archived'
  author_id: number
  views_count: number
  helpful_count: number
  not_helpful_count: number
  is_public: boolean
  published_at: string | null
  category?: KbCategory
  author?: User
  created_at: string
}

export interface ServiceCatalogItem {
  id: number
  name: string
  slug: string
  description: string
  category: string | null
  icon: string | null
  form_schema: Record<string, unknown> | null
  is_active: boolean
  sort_order: number
  approval_required: boolean
  estimated_days: number | null
  requires_approval: boolean
  approval_workflow_id: number | null
}

export interface ApprovalWorkflow {
  id: number
  name: string
  description: string | null
  is_active: boolean
  steps: ApprovalWorkflowStep[]
}

export interface ApprovalWorkflowStep {
  id?: number
  step_order: number
  approver_type: 'user' | 'role' | 'department_head'
  approver_id: number | null
  approver_role: string | null
  auto_approve_after_hours: number | null
}

export interface Approval {
  id: number
  approvable_type: string
  approvable_id: number
  approvable?: ServiceCatalogItem | Record<string, unknown>
  workflow: ApprovalWorkflow
  current_step: number
  status: 'pending' | 'approved' | 'rejected' | 'canceled'
  requested_by: number
  requester?: User
  actions: ApprovalAction[]
  created_at: string
  updated_at: string
}

export interface ApprovalAction {
  id: number
  step_order: number
  approver_id: number
  approver?: User
  action: 'approved' | 'rejected' | 'delegated'
  comment: string | null
  acted_at: string
}

export interface Notification {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

export interface ActivityLog {
  id: number
  user: {
    id: number
    name: string
    avatar_url: string | null
  }
  action: 'created' | 'updated' | 'commented' | 'assigned' | 'closed' | 'reopened'
  subject_type: string
  subject_id: number
  description: string
  properties: {
    ticket_id?: number
    ticket_number?: string
    ticket_title?: string
    field?: string
    old_value?: string
    new_value?: string
    display_value?: string
    assignee_id?: number
    assignee_name?: string
    is_internal?: boolean
    [key: string]: unknown
  } | null
  created_at: string
}

export interface AgentGroup {
  id: number
  name: string
  description: string | null
  is_active: boolean
  members?: { id: number; name: string; email: string; avatar_url: string | null }[]
  members_count?: number
  created_at: string
}

export interface Scenario {
  id: number
  name: string
  description: string | null
  actions: { field: string; value: any }[]
  is_active: boolean
  created_at: string
}

// ─── Automation Rules ──────────────────────────────────────────────

export type AutomationTriggerEvent =
  | 'ticket_created'
  | 'ticket_updated'
  | 'ticket_assigned'
  | 'ticket_closed'
  | 'ticket_reopened'
  | 'sla_approaching'
  | 'sla_breached'
  | 'comment_added'
  | 'time_based'

export interface AutomationCondition {
  field: string
  operator: string
  value: any
}

export type AutomationConditionGroup = AutomationCondition[]

export interface AutomationAction {
  type: string
  field?: string
  value?: any
  to?: string
  template?: string
  url?: string
  payload?: Record<string, any>
}

export interface AutomationRule {
  id: number
  name: string
  description: string | null
  is_active: boolean
  execution_order: number
  stop_on_match: boolean
  trigger_event: AutomationTriggerEvent
  conditions: AutomationConditionGroup[]
  actions: AutomationAction[]
  last_triggered_at: string | null
  trigger_count: number
  created_at: string
  updated_at: string
}

export interface AutomationLog {
  id: number
  ticket_id: number | null
  ticket?: {
    id: number
    ticket_number: string
    title: string
  } | null
  trigger_event: string
  conditions_matched: boolean
  actions_executed: {
    type: string
    success: boolean
    result?: string
    error?: string
  }[] | null
  error: string | null
  executed_at: string
}

export interface CannedResponse {
  id: number
  user_id: number | null
  title: string
  content: string
  category: string | null
  visibility: 'personal' | 'team' | 'global'
  shortcut: string | null
  usage_count: number
  user?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface AiSuggestion {
  id: number
  type: 'classification' | 'response' | 'kb_generation' | 'chatbot'
  input: string
  output: string
  confidence: number | null
  was_accepted: boolean | null
  created_at: string
}

export interface TicketFormField {
  id: number
  field_key: string
  label: string
  field_type: 'text' | 'textarea' | 'rich_text' | 'select' | 'number' | 'date' | 'checkbox' | 'email' | 'phone' | 'url' | 'tags' | 'file'
  is_visible: boolean
  is_required: boolean
  is_system: boolean
  sort_order: number
  options: { label: string; value: string }[] | null
  default_value: string | null
  placeholder: string | null
  section: 'main' | 'details'
  help_text: string | null
  role_visibility: string[] | null
}

// ─── Reports ────────────────────────────────────────────────────────

export interface ReportFilter {
  field: string
  operator: string
  value: any
}

export interface ReportDateRange {
  type: 'last_7_days' | 'last_30_days' | 'last_90_days' | 'this_month' | 'last_month' | 'this_year' | 'custom'
  start?: string
  end?: string
}

export interface ReportConfig {
  entity: 'tickets' | 'agents' | 'categories'
  filters: ReportFilter[]
  group_by: string | null
  metrics: string[]
  date_range: ReportDateRange | null
  chart_type: 'bar' | 'line' | 'pie' | 'table'
  columns: string[]
}

export interface SavedReport {
  id: number
  tenant_id: number
  user_id: number
  name: string
  description: string | null
  report_type: 'tickets' | 'agents' | 'sla' | 'categories' | 'trends' | 'custom'
  config: ReportConfig
  is_shared: boolean
  schedule_cron: string | null
  schedule_emails: string[] | null
  last_run_at: string | null
  user?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface ReportResultRow {
  group_key?: string
  group_value?: any
  group_label?: string
  count?: number
  avg_resolution_time?: number | null
  avg_response_time?: number | null
  sla_compliance_rate?: number | null
  avg_rating?: number | null
  total_time_spent?: number
  [key: string]: any
}

export interface ReportResult {
  data: ReportResultRow[]
  summary: Record<string, any>
  meta: {
    query_time_ms: number
    row_count: number
  }
}

export type ProblemStatus = 'logged' | 'categorized' | 'investigating' | 'root_cause_identified' | 'known_error' | 'resolved' | 'closed'
export type ProblemPriority = 'low' | 'medium' | 'high' | 'critical'
export type ProblemImpact = 'low' | 'medium' | 'high' | 'extensive'
export type ProblemUrgency = 'low' | 'medium' | 'high' | 'critical'

export interface Problem {
  id: number
  title: string
  description: string
  status: ProblemStatus
  priority: ProblemPriority
  impact: ProblemImpact
  urgency: ProblemUrgency
  category_id: number | null
  assigned_to: number | null
  department_id: number | null
  root_cause: string | null
  workaround: string | null
  resolution: string | null
  is_known_error: boolean
  known_error_id: string | null
  related_incidents_count: number
  detected_at: string | null
  resolved_at: string | null
  closed_at: string | null
  category?: Category
  assignee?: User
  department?: Department
  tickets?: Ticket[]
  known_errors?: KnownError[]
  created_at: string
  updated_at: string
}

export interface KnownError {
  id: number
  problem_id: number | null
  title: string
  description: string
  workaround: string | null
  root_cause: string | null
  status: 'open' | 'in_progress' | 'resolved'
  category_id: number | null
  problem?: Problem
  category?: Category
  created_at: string
  updated_at: string
}

export type IntegrationProvider = 'slack' | 'teams' | 'generic_webhook'

export type IntegrationEvent = 'ticket_created' | 'ticket_assigned' | 'ticket_closed' | 'sla_breach' | 'ticket_commented'

export interface IntegrationConfig {
  incoming_webhook_url: string
  channel?: string
  bot_token?: string
  signing_secret?: string
  security_token?: string
  [key: string]: unknown
}

export interface Integration {
  id: number
  tenant_id: number
  provider: IntegrationProvider
  name: string
  config: IntegrationConfig
  events: IntegrationEvent[]
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface SatisfactionSurvey {
  id: number
  ticket_id: number
  ticket?: Ticket
  user_id: number
  user?: User
  rating: number | null
  comment: string | null
  token: string
  responded_at: string | null
  sent_at: string | null
  created_at: string
}

// ─── Change Management ──────────────────────────────────────────────

export type ChangeRequestType = 'standard' | 'normal' | 'emergency'
export type ChangeRequestStatus = 'draft' | 'submitted' | 'assessment' | 'cab_review' | 'approved' | 'rejected' | 'scheduled' | 'implementing' | 'implemented' | 'review' | 'closed'
export type ChangeRequestPriority = 'low' | 'medium' | 'high' | 'critical'
export type ChangeRequestRiskLevel = 'low' | 'medium' | 'high' | 'very_high'
export type ChangeRequestImpact = 'low' | 'medium' | 'high' | 'extensive'

export interface ChangeRequestApproval {
  id: number
  approver_id: number
  approver?: { id: number; name: string; email: string; avatar_url: string | null }
  role: string
  status: 'pending' | 'approved' | 'rejected'
  comment: string | null
  decided_at: string | null
  created_at: string | null
}

export interface ChangeRequest {
  id: number
  title: string
  description: string
  type: ChangeRequestType
  status: ChangeRequestStatus
  priority: ChangeRequestPriority
  risk_level: ChangeRequestRiskLevel
  impact: ChangeRequestImpact
  category_id: number | null
  requested_by: number
  assigned_to: number | null
  department_id: number | null
  reason_for_change: string
  implementation_plan: string | null
  rollback_plan: string | null
  test_plan: string | null
  risk_assessment: Record<string, any> | null
  scheduled_start: string | null
  scheduled_end: string | null
  actual_start: string | null
  actual_end: string | null
  review_notes: string | null
  cab_decision: string | null
  cab_decided_by: number | null
  cab_decided_at: string | null
  category?: Category
  requester?: User
  assignee?: User
  department?: Department
  cab_decider?: User
  tickets?: {
    id: number
    ticket_number: string
    title: string
    status: string
    priority: string
    relationship_type: string
  }[]
  approvals?: ChangeRequestApproval[]
  created_at: string
  updated_at: string
}

// ─── Assets / CMDB ─────────────────────────────────────────────────

export interface AssetTypeField {
  name: string
  label: string
  type: 'text' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox' | 'url' | 'email'
  options?: string[]
  required?: boolean
}

export interface AssetType {
  id: number
  name: string
  icon: string | null
  fields: AssetTypeField[] | null
  assets_count?: number
  created_at: string
  updated_at: string
}

export type AssetStatus = 'active' | 'inactive' | 'maintenance' | 'retired' | 'lost' | 'disposed'
export type AssetCondition = 'new' | 'good' | 'fair' | 'poor' | 'broken'

export interface Asset {
  id: number
  asset_type_id: number
  name: string
  asset_tag: string
  serial_number: string | null
  status: AssetStatus
  condition: AssetCondition
  assigned_to: number | null
  department_id: number | null
  location: string | null
  purchase_date: string | null
  purchase_cost: number | null
  warranty_expiry: string | null
  vendor: string | null
  manufacturer: string | null
  model: string | null
  ip_address: string | null
  mac_address: string | null
  custom_fields: Record<string, any> | null
  notes: string | null
  asset_type?: AssetType
  assignee?: User
  department?: Department
  tickets?: Ticket[]
  created_at: string
  updated_at: string
}

export interface AssetRelationship {
  id: number
  direction: 'outgoing' | 'incoming'
  relationship_type: 'contains' | 'depends_on' | 'connected_to' | 'installed_on' | 'runs_on'
  related_asset: {
    id: number
    name: string
    asset_tag: string
    status: string
  }
  created_at: string
}

export interface AssetLog {
  id: number
  action: string
  description: string
  old_values: Record<string, any> | null
  new_values: Record<string, any> | null
  user: {
    id: number
    name: string
    avatar_url: string | null
  } | null
  created_at: string
}
