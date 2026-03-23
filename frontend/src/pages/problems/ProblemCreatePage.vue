<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { Notify } from 'quasar'
import { createProblem } from '@/api/problems'
import { getCategories } from '@/api/categories'
import { getAgents } from '@/api/users'
import { getDepartments } from '@/api/departments'
import { getTickets } from '@/api/tickets'
import type { Category, User, Department, Ticket } from '@/types'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

const loading = ref(false)
const categories = ref<Category[]>([])
const agents = ref<User[]>([])
const departments = ref<Department[]>([])

const form = reactive({
  title: '',
  description: '',
  priority: 'medium',
  impact: 'medium',
  urgency: 'medium',
  category_id: null as number | null,
  assigned_to: null as number | null,
  department_id: null as number | null,
  detected_at: '',
  ticket_ids: [] as number[],
})

// Ticket linking
const ticketSearch = ref('')
const ticketSearchResults = ref<Ticket[]>([])
const ticketSearching = ref(false)
const linkedTickets = ref<Ticket[]>([])

async function onSearchTickets() {
  if (!ticketSearch.value.trim()) return
  ticketSearching.value = true
  try {
    const res = await getTickets({ search: ticketSearch.value, per_page: 10 })
    ticketSearchResults.value = (res.data || []).filter(
      (t: Ticket) => !form.ticket_ids.includes(t.id)
    )
  } catch { /* ignore */ }
  finally { ticketSearching.value = false }
}

function addTicket(ticket: Ticket) {
  if (!form.ticket_ids.includes(ticket.id)) {
    form.ticket_ids.push(ticket.id)
    linkedTickets.value.push(ticket)
  }
  ticketSearch.value = ''
  ticketSearchResults.value = []
}

function removeTicket(id: number) {
  form.ticket_ids = form.ticket_ids.filter(tid => tid !== id)
  linkedTickets.value = linkedTickets.value.filter(t => t.id !== id)
}

async function onSubmit() {
  loading.value = true
  try {
    const payload: any = { ...form }
    if (!payload.detected_at) delete payload.detected_at
    if (payload.ticket_ids.length === 0) delete payload.ticket_ids

    const res = await createProblem(payload)
    Notify.create({ type: 'positive', message: t('problems.created') })
    router.push({ name: 'problem-detail', params: { id: res.data.id } })
  } catch { /* handled by interceptor */ }
  finally { loading.value = false }
}

onMounted(async () => {
  try {
    const [catRes, agentRes, deptRes] = await Promise.all([
      getCategories(),
      getAgents(),
      getDepartments(),
    ])
    categories.value = catRes.data || []
    agents.value = agentRes.data || []
    departments.value = deptRes.data || []
  } catch { /* ignore */ }

  // Pre-link ticket if coming from ticket detail
  const ticketId = route.query.ticket_id
  const ticketTitle = route.query.ticket_title
  if (ticketId) {
    try {
      const res = await getTickets({ search: ticketId, per_page: 1 })
      const ticket = res.data?.find((t: any) => t.id === Number(ticketId))
      if (ticket) {
        addTicket(ticket)
      }
    } catch { /* ignore */ }
    if (ticketTitle && typeof ticketTitle === 'string') {
      form.title = `Problema: ${ticketTitle}`
    }
  }
})
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <q-btn flat icon="arrow_back" :label="t('common.back')" @click="router.back()" />
      <div class="text-h5 text-weight-bold q-ml-md">{{ t('problems.create') }}</div>
    </div>

    <q-form @submit.prevent="onSubmit">
      <div class="row q-col-gutter-md">
        <!-- Main form -->
        <div class="col-12 col-md-8">
          <q-card flat bordered>
            <q-card-section>
              <q-input
                v-model="form.title"
                :label="t('problems.fields.title')"
                outlined
                :rules="[(v: string) => !!v || t('common.required')]"
                class="q-mb-md"
              />

              <q-input
                v-model="form.description"
                :label="t('problems.fields.description')"
                type="textarea"
                outlined
                autogrow
                :rules="[(v: string) => !!v || t('common.required')]"
                class="q-mb-md"
              />

              <q-input
                v-model="form.detected_at"
                :label="t('problems.fields.detectedAt')"
                type="date"
                outlined
                class="q-mb-md"
              />
            </q-card-section>
          </q-card>

          <!-- Link tickets -->
          <q-card flat bordered class="q-mt-md">
            <q-card-section>
              <div class="text-subtitle1 text-weight-medium q-mb-sm">{{ t('problems.linkTickets') }}</div>
              <div class="row q-col-gutter-sm items-end q-mb-md">
                <div class="col">
                  <q-input
                    v-model="ticketSearch"
                    :placeholder="t('problems.searchTickets')"
                    dense outlined
                    @keyup.enter="onSearchTickets"
                  >
                    <template #append>
                      <q-btn flat dense icon="search" :loading="ticketSearching" @click="onSearchTickets" />
                    </template>
                  </q-input>
                </div>
              </div>

              <!-- Search results -->
              <q-list v-if="ticketSearchResults.length" bordered separator class="q-mb-md rounded-borders">
                <q-item v-for="ticket in ticketSearchResults" :key="ticket.id" clickable @click="addTicket(ticket)">
                  <q-item-section>
                    <q-item-label>{{ ticket.ticket_number }} - {{ ticket.title }}</q-item-label>
                    <q-item-label caption>{{ ticket.status }} | {{ ticket.priority }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-icon name="add_circle" color="primary" />
                  </q-item-section>
                </q-item>
              </q-list>

              <!-- Linked tickets -->
              <div v-if="linkedTickets.length">
                <q-chip
                  v-for="ticket in linkedTickets"
                  :key="ticket.id"
                  removable
                  @remove="removeTicket(ticket.id)"
                  color="primary"
                  text-color="white"
                >
                  {{ ticket.ticket_number }} - {{ ticket.title }}
                </q-chip>
              </div>
              <div v-else class="text-grey text-caption">{{ t('problems.noLinkedTickets') }}</div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-md-4">
          <q-card flat bordered>
            <q-card-section>
              <q-select
                v-model="form.priority"
                :options="[
                  { label: t('problems.priorities.low'), value: 'low' },
                  { label: t('problems.priorities.medium'), value: 'medium' },
                  { label: t('problems.priorities.high'), value: 'high' },
                  { label: t('problems.priorities.critical'), value: 'critical' },
                ]"
                :label="t('common.priority')"
                emit-value map-options outlined
                class="q-mb-md"
              />

              <q-select
                v-model="form.impact"
                :options="[
                  { label: t('problems.impacts.low'), value: 'low' },
                  { label: t('problems.impacts.medium'), value: 'medium' },
                  { label: t('problems.impacts.high'), value: 'high' },
                  { label: t('problems.impacts.extensive'), value: 'extensive' },
                ]"
                :label="t('problems.fields.impact')"
                emit-value map-options outlined
                class="q-mb-md"
              />

              <q-select
                v-model="form.urgency"
                :options="[
                  { label: t('problems.urgencies.low'), value: 'low' },
                  { label: t('problems.urgencies.medium'), value: 'medium' },
                  { label: t('problems.urgencies.high'), value: 'high' },
                  { label: t('problems.urgencies.critical'), value: 'critical' },
                ]"
                :label="t('problems.fields.urgency')"
                emit-value map-options outlined
                class="q-mb-md"
              />

              <q-select
                v-model="form.category_id"
                :options="categories.map(c => ({ label: c.name, value: c.id }))"
                :label="t('common.category')"
                emit-value map-options outlined clearable
                class="q-mb-md"
              />

              <q-select
                v-model="form.assigned_to"
                :options="agents.map(a => ({ label: a.name, value: a.id }))"
                :label="t('tickets.assignedTo')"
                emit-value map-options outlined clearable
                class="q-mb-md"
              />

              <q-select
                v-model="form.department_id"
                :options="departments.map(d => ({ label: d.name, value: d.id }))"
                :label="t('problems.fields.department')"
                emit-value map-options outlined clearable
              />
            </q-card-section>
          </q-card>

          <q-btn
            type="submit"
            color="primary"
            :label="t('problems.create')"
            :loading="loading"
            class="full-width q-mt-md"
            size="lg"
          />
        </div>
      </div>
    </q-form>
  </q-page>
</template>
