import { onUnmounted } from 'vue'
import { getEcho } from '@/utils/echo'
import type { Ticket, TicketComment } from '@/types'

interface TicketCreatedPayload {
  id: number
  ticket_number: string
  title: string
  status: string
  priority: string
  type: string
  source: string
  requester: { id: number; name: string } | null
  category: string | null
  assigned_to: number | null
  created_at: string
}

interface TicketUpdatedPayload {
  id: number
  ticket_number: string
  title: string
  status: string
  priority: string
  type: string
  assigned_to: number | null
  assignee_name: string | null
  category: string | null
  changed_fields: string[]
  updated_at: string
}

interface TicketCommentPayload {
  id: number
  ticket_id: number
  user_id: number
  body: string
  is_internal: boolean
  user: { id: number; name: string; avatar_url: string | null } | null
  created_at: string
}

/**
 * Subscribe to tenant-wide events (new tickets, ticket updates).
 * Use in dashboard, ticket list, and main layout.
 */
export function useTenantChannel(
  tenantId: number,
  handlers: {
    onTicketCreated?: (data: TicketCreatedPayload) => void
    onTicketUpdated?: (data: TicketUpdatedPayload) => void
  },
) {
  const echo = getEcho()
  if (!echo) return null
  const channelName = `tenant.${tenantId}`

  const channel = echo.private(channelName)

  if (handlers.onTicketCreated) {
    channel.listen('TicketCreated', (e: any) => handlers.onTicketCreated!(e))
  }
  if (handlers.onTicketUpdated) {
    channel.listen('TicketUpdated', (e: any) => handlers.onTicketUpdated!(e))
  }

  onUnmounted(() => {
    echo.leave(channelName)
  })

  return channel
}

/**
 * Subscribe to a specific ticket's events (updates, new comments).
 * Use in ticket detail page.
 */
export function useTicketChannel(
  ticketId: number,
  handlers: {
    onUpdated?: (data: TicketUpdatedPayload) => void
    onCommentAdded?: (data: TicketCommentPayload) => void
  },
) {
  const echo = getEcho()
  if (!echo) return null
  const channelName = `ticket.${ticketId}`

  const channel = echo.private(channelName)

  if (handlers.onUpdated) {
    channel.listen('TicketUpdated', (e: any) => handlers.onUpdated!(e))
  }
  if (handlers.onCommentAdded) {
    channel.listen('TicketCommentAdded', (e: any) => handlers.onCommentAdded!(e))
  }

  onUnmounted(() => {
    echo.leave(channelName)
  })

  return channel
}

/**
 * Subscribe to user-specific events (notifications).
 * Use in main layout for notification badge.
 */
export function useUserChannel(
  userId: number,
  handlers: {
    onNotification?: (data: any) => void
  },
) {
  const echo = getEcho()
  if (!echo) return null
  const channelName = `user.${userId}`

  const channel = echo.private(channelName)

  if (handlers.onNotification) {
    channel.listen('NotificationCreated', (e: any) => handlers.onNotification!(e))
  }

  onUnmounted(() => {
    echo.leave(channelName)
  })

  return channel
}
