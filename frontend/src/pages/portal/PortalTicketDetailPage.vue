<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getTicket, addComment } from '@/api/tickets'
import type { Ticket } from '@/types'
import { Notify } from 'quasar'

const props = defineProps<{ id: string | number }>()
const route = useRoute()
const router = useRouter()
const tenantSlug = route.params.tenantSlug as string

const ticket = ref<Ticket | null>(null)
const loading = ref(true)
const commentBody = ref('')
const sendingComment = ref(false)

const statusLabels: Record<string, string> = {
  open: 'Abierto',
  in_progress: 'En Progreso',
  pending: 'Pendiente',
  resolved: 'Resuelto',
  closed: 'Cerrado',
}

const statusColors: Record<string, string> = {
  open: 'blue',
  in_progress: 'orange',
  pending: 'grey',
  resolved: 'green',
  closed: 'grey-6',
}

const priorityLabels: Record<string, string> = {
  low: 'Baja',
  medium: 'Media',
  high: 'Alta',
  urgent: 'Urgente',
}

// Filter out internal notes for end users
const publicComments = computed(() =>
  (ticket.value?.comments || []).filter(c => !c.is_internal)
)

onMounted(async () => {
  try {
    const res = await getTicket(Number(props.id))
    ticket.value = res.data
  } catch {
    router.push(`/portal/${tenantSlug}/tickets`)
  } finally {
    loading.value = false
  }
})

async function submitComment() {
  if (!commentBody.value.trim() || !ticket.value) return
  sendingComment.value = true
  try {
    const res = await addComment(ticket.value.id, {
      body: commentBody.value.trim(),
      is_internal: false,
    })
    ticket.value.comments = [...(ticket.value.comments || []), res.data]
    commentBody.value = ''
    Notify.create({ type: 'positive', message: 'Comentario agregado' })
  } catch {
    // handled by interceptor
  } finally {
    sendingComment.value = false
  }
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <q-page class="portal-page">
    <div class="portal-container">
      <!-- Loading -->
      <div v-if="loading" class="text-center q-pa-xl">
        <q-spinner size="40px" color="primary" />
      </div>

      <template v-else-if="ticket">
        <!-- Header -->
        <div class="row items-center q-mb-md q-gutter-sm">
          <q-btn flat round icon="arrow_back" :to="`/portal/${tenantSlug}/tickets`" />
          <span class="text-grey-7">{{ ticket.ticket_number }}</span>
          <q-badge :color="statusColors[ticket.status]" :label="statusLabels[ticket.status]" />
        </div>

        <q-card class="q-mb-md">
          <q-card-section>
            <div class="text-h5 text-weight-bold">{{ ticket.title }}</div>
            <div class="text-caption text-grey-7 q-mt-xs">
              Creado el {{ formatDate(ticket.created_at) }}
              <span v-if="ticket.category"> &middot; {{ ticket.category.name }}</span>
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <div style="white-space: pre-wrap;">{{ ticket.description }}</div>
          </q-card-section>

          <q-separator />

          <!-- Ticket details sidebar -->
          <q-card-section>
            <div class="row q-col-gutter-md">
              <div class="col-6 col-sm-3">
                <div class="text-caption text-grey-7">Estado</div>
                <q-badge :color="statusColors[ticket.status]" :label="statusLabels[ticket.status]" />
              </div>
              <div class="col-6 col-sm-3">
                <div class="text-caption text-grey-7">Prioridad</div>
                <div>{{ priorityLabels[ticket.priority] }}</div>
              </div>
              <div class="col-6 col-sm-3">
                <div class="text-caption text-grey-7">Tipo</div>
                <div class="text-capitalize">{{ ticket.type }}</div>
              </div>
              <div class="col-6 col-sm-3" v-if="ticket.assignee">
                <div class="text-caption text-grey-7">Asignado a</div>
                <div>{{ ticket.assignee.name }}</div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- Comments -->
        <div class="text-h6 text-weight-bold q-mb-md">Comentarios ({{ publicComments.length }})</div>

        <div v-if="publicComments.length === 0" class="text-grey-6 q-mb-md">
          Aun no hay comentarios.
        </div>

        <q-card v-for="comment in publicComments" :key="comment.id" flat bordered class="q-mb-sm">
          <q-card-section>
            <div class="row items-center q-gutter-sm q-mb-xs">
              <q-avatar size="28px" color="primary" text-color="white" font-size="12px">
                {{ comment.user?.name?.charAt(0)?.toUpperCase() }}
              </q-avatar>
              <span class="text-weight-medium">{{ comment.user?.name }}</span>
              <span class="text-caption text-grey-6">{{ formatDate(comment.created_at) }}</span>
            </div>
            <div style="white-space: pre-wrap; margin-left: 40px;">{{ comment.body }}</div>
          </q-card-section>
        </q-card>

        <!-- Add comment -->
        <q-card v-if="ticket.status !== 'closed'" class="q-mt-md">
          <q-card-section>
            <q-input
              v-model="commentBody"
              label="Agregar comentario"
              type="textarea"
              outlined
              autogrow
              :disable="sendingComment"
            />
            <div class="row justify-end q-mt-sm">
              <q-btn
                color="primary"
                label="Enviar"
                no-caps
                icon="send"
                :loading="sendingComment"
                :disable="!commentBody.trim()"
                @click="submitComment"
              />
            </div>
          </q-card-section>
        </q-card>
      </template>
    </div>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 32px 24px;
}
.body--dark .portal-page {
  background: #121212;
}
</style>
