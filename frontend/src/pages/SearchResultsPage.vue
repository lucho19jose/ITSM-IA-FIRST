<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getTickets } from '@/api/tickets'
import { getKbArticles } from '@/api/kb'
import type { Ticket, KbArticle } from '@/types'

const route = useRoute()
const router = useRouter()

const searchTerm = ref('')
const loading = ref(false)
const activeTab = ref('all')

const tickets = ref<Ticket[]>([])
const ticketTotal = ref(0)
const articles = ref<KbArticle[]>([])
const articleTotal = ref(0)

const totalResults = computed(() => ticketTotal.value + articleTotal.value)

onMounted(() => {
  searchTerm.value = String(route.query.term || '')
  if (searchTerm.value) doSearch()
})

watch(() => route.query.term, (val) => {
  searchTerm.value = String(val || '')
  if (searchTerm.value) doSearch()
})

async function doSearch() {
  if (!searchTerm.value.trim()) return
  loading.value = true
  try {
    const [ticketRes, articleRes] = await Promise.all([
      getTickets({ search: searchTerm.value, per_page: 10, sort: 'created_at', direction: 'desc' }),
      getKbArticles({ search: searchTerm.value, per_page: 5 }),
    ])
    tickets.value = ticketRes.data
    ticketTotal.value = ticketRes.meta.total
    articles.value = articleRes.data
    articleTotal.value = articleRes.meta.total
  } catch {
    /* handled */
  } finally {
    loading.value = false
  }
}

function onSearchSubmit() {
  if (!searchTerm.value.trim()) return
  router.replace({ path: '/search', query: { term: searchTerm.value.trim() } })
}

function highlightTerm(text: string | null | undefined): string {
  if (!text || !searchTerm.value) return text || ''
  const escaped = searchTerm.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark>$1</mark>')
}

function getExcerpt(ticket: Ticket): string {
  const desc = ticket.description?.replace(/<[^>]*>/g, '') || ''
  if (!searchTerm.value) return desc.substring(0, 120)
  const idx = desc.toLowerCase().indexOf(searchTerm.value.toLowerCase())
  if (idx < 0) return desc.substring(0, 120)
  const start = Math.max(0, idx - 40)
  const end = Math.min(desc.length, idx + searchTerm.value.length + 80)
  return (start > 0 ? '...' : '') + desc.substring(start, end) + (end < desc.length ? '...' : '')
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `hace ${mins}m`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours}h`
  const days = Math.floor(hours / 24)
  if (days < 30) return `hace ${days} dias`
  const months = Math.floor(days / 30)
  return `hace ${months} meses`
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    open: 'Abierto', in_progress: 'En Progreso', pending: 'Pendiente',
    resolved: 'Resuelto', closed: 'Cerrado',
  }
  return labels[status] || status
}

function getInitial(name?: string): string {
  return name ? name.charAt(0).toUpperCase() : '?'
}

function getAvatarColor(name?: string): string {
  if (!name) return '#9e9e9e'
  const colors = ['#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#009688', '#4caf50', '#ff9800', '#795548']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

function goToTicket(id: number) {
  router.push(`/tickets/${id}`)
}

function goToArticle(id: number) {
  router.push(`/kb/articles/${id}`)
}

function viewAllTickets() {
  router.push({ path: '/tickets', query: { search: searchTerm.value } })
}
</script>

<template>
  <q-page class="search-results-page">
    <!-- Search input area -->
    <div class="search-header">
      <div class="search-input-wrapper">
        <q-input
          v-model="searchTerm"
          placeholder="Buscar..."
          outlined
          class="search-main-input"
          autofocus
          @keyup.enter="onSearchSubmit"
        >
          <template v-slot:prepend>
            <q-icon name="search" size="22px" color="grey-6" />
          </template>
          <template v-slot:append>
            <span v-if="!loading && totalResults > 0" class="result-count">{{ totalResults }}</span>
            <q-spinner-dots v-if="loading" size="20px" color="primary" />
          </template>
        </q-input>
      </div>

      <!-- Tabs -->
      <div class="search-tabs">
        <q-tabs
          v-model="activeTab"
          no-caps dense
          active-color="primary"
          indicator-color="primary"
          class="text-grey-7"
          align="center"
        >
          <q-tab name="all" :label="`Todos los resultados`" />
          <q-tab name="tickets" :label="`Tickets (${ticketTotal})`" />
          <q-tab name="articles" :label="`Soluciones (${articleTotal})`" />
        </q-tabs>
      </div>

      <div class="search-sort text-caption text-grey-7">
        Ordenar por: <strong>Relevancia</strong>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <!-- Results -->
    <div v-else class="search-results">
      <!-- No results -->
      <div v-if="totalResults === 0 && searchTerm" class="text-center text-grey q-pa-xl">
        <q-icon name="search_off" size="64px" class="q-mb-md" color="grey-4" />
        <div class="text-h6">No se encontraron resultados</div>
        <div class="text-body2 q-mt-sm">Intenta con otros terminos de busqueda</div>
      </div>

      <!-- Tickets Section -->
      <div v-if="(activeTab === 'all' || activeTab === 'tickets') && tickets.length > 0" class="results-section">
        <div class="section-header">
          <q-icon name="confirmation_number" size="20px" class="q-mr-sm" />
          TICKETS
        </div>

        <div
          v-for="ticket in tickets"
          :key="ticket.id"
          class="result-item"
          @click="goToTicket(ticket.id)"
        >
          <div class="row no-wrap">
            <!-- Avatar -->
            <div
              class="result-avatar q-mr-md"
              :style="{ backgroundColor: getAvatarColor(ticket.requester?.name) }"
            >
              {{ getInitial(ticket.requester?.name) }}
            </div>

            <!-- Main content -->
            <div class="col">
              <div class="result-title">
                <span v-html="highlightTerm(ticket.title)" />
                <span class="result-ticket-number">#{{ ticket.ticket_number }}</span>
              </div>
              <div class="result-meta">
                De: {{ ticket.requester?.name || 'Desconocido' }}, {{ getStatusLabel(ticket.status) }}: {{ timeAgo(ticket.created_at) }}
              </div>
              <div class="result-excerpt" v-html="highlightTerm(getExcerpt(ticket))"></div>
            </div>

            <!-- Right info -->
            <div class="result-right-info">
              <div class="info-row">
                <span class="info-label">Agente:</span>
                <span class="info-value">{{ ticket.assignee?.name || 'Sin asignar' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value">{{ getStatusLabel(ticket.status) }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Grupo:</span>
                <span class="info-value">{{ ticket.department?.name || 'Sin grupo' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- View all tickets link -->
        <div v-if="ticketTotal > tickets.length" class="view-all-link" @click="viewAllTickets">
          Ver todos ({{ ticketTotal }})
        </div>
      </div>

      <!-- KB Articles Section -->
      <div v-if="(activeTab === 'all' || activeTab === 'articles') && articles.length > 0" class="results-section">
        <div class="section-header">
          <q-icon name="menu_book" size="20px" class="q-mr-sm" />
          SOLUCIONES
        </div>

        <div
          v-for="article in articles"
          :key="article.id"
          class="result-item"
          @click="goToArticle(article.id)"
        >
          <div class="row no-wrap">
            <div
              class="result-avatar q-mr-md"
              style="background-color: #009688;"
            >
              <q-icon name="article" color="white" size="18px" />
            </div>

            <div class="col">
              <div class="result-title">
                <span v-html="highlightTerm(article.title)" />
              </div>
              <div class="result-meta">
                Por: {{ article.author?.name || 'Desconocido' }} · {{ article.views_count }} vistas · {{ article.category?.name || '' }}
              </div>
              <div class="result-excerpt" v-html="highlightTerm(article.excerpt || '')"></div>
            </div>

            <div class="result-right-info">
              <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value">{{ article.status === 'published' ? 'Publicado' : article.status === 'draft' ? 'Borrador' : 'Archivado' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Categoria:</span>
                <span class="info-value">{{ article.category?.name || '-' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<style scoped>
.search-results-page {
  background: #f5f7fa;
  padding: 0 !important;
}

/* Search header area */
.search-header {
  background: #fff;
  border-bottom: 1px solid #e8ecf0;
  padding-top: 24px;
}

.search-input-wrapper {
  max-width: 700px;
  margin: 0 auto;
  padding: 0 24px;
}

.search-main-input :deep(.q-field__control) {
  height: 48px;
  font-size: 16px;
}

.search-main-input :deep(.q-field__marginal) {
  height: 48px;
}

.result-count {
  font-size: 13px;
  color: #6b7280;
  background: #f0f0f0;
  padding: 2px 8px;
  border-radius: 4px;
}

.search-tabs {
  max-width: 700px;
  margin: 16px auto 0;
  padding: 0 24px;
}

.search-tabs :deep(.q-tab) {
  min-height: 36px;
  font-size: 13px;
}

.search-sort {
  max-width: 900px;
  margin: 0 auto;
  padding: 10px 24px;
}

/* Results */
.search-results {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 24px 32px;
}

.results-section {
  background: #fff;
  border: 1px solid #e8ecf0;
  border-radius: 8px;
  margin-top: 16px;
  overflow: hidden;
}

.section-header {
  display: flex;
  align-items: center;
  padding: 14px 20px;
  font-size: 12px;
  font-weight: 700;
  color: #374151;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #f0f0f0;
}

.result-item {
  padding: 16px 20px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background-color 0.15s;
}

.result-item:last-child {
  border-bottom: none;
}

.result-item:hover {
  background: #f8fafc;
}

.result-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 600;
  font-size: 15px;
  flex-shrink: 0;
  margin-top: 2px;
}

.result-title {
  font-size: 14px;
  font-weight: 600;
  color: #1976d2;
  margin-bottom: 2px;
  line-height: 1.4;
}

.result-ticket-number {
  font-weight: 400;
  color: #6b7280;
  margin-left: 6px;
  font-size: 13px;
}

.result-meta {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 4px;
}

.result-excerpt {
  font-size: 13px;
  color: #9ca3af;
  line-height: 1.4;
}

.result-excerpt :deep(mark) {
  background: #fef08a;
  color: #1a1a2e;
  padding: 0 1px;
  border-radius: 2px;
}

.result-title :deep(mark) {
  background: #fef08a;
  color: #1976d2;
  padding: 0 1px;
  border-radius: 2px;
}

.result-right-info {
  flex-shrink: 0;
  min-width: 200px;
  padding-left: 24px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-row {
  display: flex;
  font-size: 12px;
  gap: 8px;
}

.info-label {
  color: #9ca3af;
  min-width: 50px;
}

.info-value {
  color: #374151;
  font-weight: 500;
}

.view-all-link {
  padding: 12px 20px;
  font-size: 13px;
  color: #1976d2;
  font-weight: 500;
  cursor: pointer;
  border-top: 1px solid #f0f0f0;
}

.view-all-link:hover {
  background: #f5f9ff;
}

@media (max-width: 768px) {
  .result-right-info {
    display: none;
  }
}
</style>
