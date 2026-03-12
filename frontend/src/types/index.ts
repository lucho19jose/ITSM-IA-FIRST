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
  satisfaction_rating: number | null
  created_at: string
  updated_at: string
}

export interface TicketComment {
  id: number
  ticket_id: number
  user_id: number
  body: string
  is_internal: boolean
  user?: User
  created_at: string
}

export interface TicketAttachment {
  id: number
  ticket_id: number
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
