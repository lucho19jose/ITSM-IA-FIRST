import { driver, type DriveStep } from 'driver.js'
import 'driver.js/dist/driver.css'

const STORAGE_KEY = 'autoservice_onboarding'

interface OnboardingState {
  dashboard?: boolean
  tickets?: boolean
  ticketDetail?: boolean
}

function getState(): OnboardingState {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')
  } catch {
    return {}
  }
}

function markCompleted(tour: keyof OnboardingState) {
  const state = getState()
  state[tour] = true
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state))
}

function hasCompleted(tour: keyof OnboardingState): boolean {
  return !!getState()[tour]
}

function runTour(tourName: keyof OnboardingState, steps: DriveStep[]) {
  if (hasCompleted(tourName)) return

  // Small delay to let the page render
  setTimeout(() => {
    const d = driver({
      showProgress: true,
      animate: true,
      allowClose: true,
      overlayColor: 'rgba(0, 0, 0, 0.5)',
      nextBtnText: 'Siguiente',
      prevBtnText: 'Anterior',
      doneBtnText: 'Listo',
      progressText: '{{current}} de {{total}}',
      onDestroyed: () => markCompleted(tourName),
      steps,
    })
    d.drive()
  }, 600)
}

export function startDashboardTour() {
  runTour('dashboard', [
    {
      element: '.header-search',
      popover: {
        title: 'Busqueda global',
        description: 'Busca tickets, articulos y servicios desde cualquier pagina. Presiona "/" para enfocar rapidamente.',
        side: 'bottom',
        align: 'center',
      },
    },
    {
      element: '.q-drawer .q-list',
      popover: {
        title: 'Menu de navegacion',
        description: 'Accede al dashboard, tickets, base de conocimiento y catalogo de servicios.',
        side: 'right',
        align: 'start',
      },
    },
    {
      element: 'a[href="/tickets/create"], button[href="/tickets/create"], .q-toolbar .q-btn[to="/tickets/create"]',
      popover: {
        title: 'Crear ticket',
        description: 'Crea un nuevo ticket de soporte rapidamente desde aqui.',
        side: 'bottom',
        align: 'center',
      },
    },
    {
      element: '.q-btn[icon="notifications"], button[aria-label="notifications"]',
      popover: {
        title: 'Notificaciones',
        description: 'Recibe alertas en tiempo real de nuevos tickets, comentarios y asignaciones.',
        side: 'bottom',
        align: 'end',
      },
    },
    {
      popover: {
        title: 'Listo para empezar!',
        description: 'Ya conoces lo basico. Explora el dashboard para ver el estado de tus tickets y metricas de rendimiento.',
      },
    },
  ])
}

export function startTicketListTour() {
  runTour('tickets', [
    {
      element: '.ticket-filters, .q-table__top',
      popover: {
        title: 'Filtros y busqueda',
        description: 'Filtra tickets por estado, prioridad, categoria o agente asignado.',
        side: 'bottom',
        align: 'start',
      },
    },
    {
      element: '.q-table tbody tr:first-child',
      popover: {
        title: 'Detalle del ticket',
        description: 'Haz clic en un ticket para ver todos los detalles, comentarios y el historial.',
        side: 'bottom',
        align: 'center',
      },
    },
    {
      popover: {
        title: 'Acciones rapidas',
        description: 'Puedes cambiar estado, prioridad y asignacion directamente desde la lista.',
      },
    },
  ])
}

export function startTicketDetailTour() {
  runTour('ticketDetail', [
    {
      element: '.ticket-properties, .q-card:first-of-type',
      popover: {
        title: 'Propiedades del ticket',
        description: 'Aqui puedes ver y editar el estado, prioridad, tipo y asignacion del ticket.',
        side: 'left',
        align: 'start',
      },
    },
    {
      element: '.ticket-comments, .comments-section',
      popover: {
        title: 'Comentarios y notas',
        description: 'Agrega respuestas publicas o notas internas (solo visibles para agentes).',
        side: 'top',
        align: 'center',
      },
    },
    {
      popover: {
        title: 'Colaboracion en tiempo real',
        description: 'Los cambios de otros agentes aparecen automaticamente gracias a WebSockets. No necesitas recargar la pagina.',
      },
    },
  ])
}

/** Reset all onboarding tours so they show again */
export function resetOnboarding() {
  localStorage.removeItem(STORAGE_KEY)
}
