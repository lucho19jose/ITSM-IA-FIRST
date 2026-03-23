<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuasar, Notify } from 'quasar'
import { getKnownErrors, createKnownError, updateKnownError, deleteKnownError, searchKnownErrors } from '@/api/knownErrors'
import { getCategories } from '@/api/categories'
import type { KnownError, Category } from '@/types'

const { t } = useI18n()
const $q = useQuasar()

const loading = ref(false)
const knownErrors = ref<KnownError[]>([])
const categories = ref<Category[]>([])

// Pagination
const currentPage = ref(1)
const perPage = ref(15)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value) || 1)

// Filters
const searchQuery = ref('')
const statusFilter = ref<string | null>(null)

const statusOptions = [
  { label: t('knownErrors.statuses.open'), value: 'open' },
  { label: t('knownErrors.statuses.in_progress'), value: 'in_progress' },
  { label: t('knownErrors.statuses.resolved'), value: 'resolved' },
]

function statusColor(status: string) {
  const colors: Record<string, string> = {
    open: 'orange', in_progress: 'blue', resolved: 'green',
  }
  return colors[status] || 'grey'
}

async function loadKnownErrors() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (searchQuery.value) params.search = searchQuery.value
    if (statusFilter.value) params.status = statusFilter.value

    const res = await getKnownErrors(params)
    knownErrors.value = res.data
    totalItems.value = res.meta.total
  } catch { /* ignore */ }
  finally { loading.value = false }
}

function onSearch() {
  currentPage.value = 1
  loadKnownErrors()
}

// ─── Create/Edit Dialog ──────────────────────────────────────────────────────
const showDialog = ref(false)
const editingId = ref<number | null>(null)
const form = reactive({
  title: '',
  description: '',
  workaround: '',
  root_cause: '',
  status: 'open',
  category_id: null as number | null,
})

function openCreate() {
  editingId.value = null
  form.title = ''
  form.description = ''
  form.workaround = ''
  form.root_cause = ''
  form.status = 'open'
  form.category_id = null
  showDialog.value = true
}

function openEdit(ke: KnownError) {
  editingId.value = ke.id
  form.title = ke.title
  form.description = ke.description
  form.workaround = ke.workaround || ''
  form.root_cause = ke.root_cause || ''
  form.status = ke.status
  form.category_id = ke.category_id
  showDialog.value = true
}

const dialogSaving = ref(false)

async function onSaveDialog() {
  dialogSaving.value = true
  try {
    if (editingId.value) {
      await updateKnownError(editingId.value, { ...form } as any)
      Notify.create({ type: 'positive', message: t('knownErrors.updated') })
    } else {
      await createKnownError({ ...form } as any)
      Notify.create({ type: 'positive', message: t('knownErrors.created') })
    }
    showDialog.value = false
    loadKnownErrors()
  } catch { /* handled by interceptor */ }
  finally { dialogSaving.value = false }
}

async function onDelete(ke: KnownError) {
  $q.dialog({
    title: t('common.confirm'),
    message: t('knownErrors.confirmDelete'),
    cancel: true,
  }).onOk(async () => {
    try {
      await deleteKnownError(ke.id)
      Notify.create({ type: 'positive', message: t('knownErrors.deleted') })
      loadKnownErrors()
    } catch { /* ignore */ }
  })
}

// ─── Expand row for detail ───────────────────────────────────────────────────
const expandedId = ref<number | null>(null)

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

onMounted(() => {
  loadKnownErrors()
  getCategories().then(res => { categories.value = res.data || [] }).catch(() => {})
})
</script>

<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ t('knownErrors.title') }}</div>
        <div class="text-caption text-grey">{{ t('knownErrors.subtitle') }}</div>
      </div>
      <q-btn color="primary" icon="add" :label="t('knownErrors.create')" @click="openCreate" />
    </div>

    <!-- Filters -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="row q-col-gutter-sm items-end">
          <div class="col-12 col-sm-6">
            <q-input
              v-model="searchQuery"
              :placeholder="t('knownErrors.searchPlaceholder')"
              dense outlined clearable
              @keyup.enter="onSearch"
            >
              <template #prepend><q-icon name="search" /></template>
            </q-input>
          </div>
          <div class="col-6 col-sm-3">
            <q-select
              v-model="statusFilter"
              :options="statusOptions"
              :label="t('common.status')"
              emit-value map-options dense outlined clearable
              @update:model-value="onSearch"
            />
          </div>
          <div class="col-6 col-sm-3">
            <q-btn flat dense icon="search" :label="t('common.search')" @click="onSearch" />
          </div>
        </div>
      </q-card-section>
    </q-card>

    <!-- Table -->
    <q-card flat bordered>
      <q-list separator>
        <q-inner-loading :showing="loading" />

        <template v-if="knownErrors.length">
          <template v-for="ke in knownErrors" :key="ke.id">
            <q-item clickable @click="toggleExpand(ke.id)">
              <q-item-section avatar>
                <q-icon name="warning" :color="statusColor(ke.status)" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-medium">{{ ke.title }}</q-item-label>
                <q-item-label caption>
                  {{ ke.description.substring(0, 120) }}{{ ke.description.length > 120 ? '...' : '' }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <div class="row items-center q-gutter-sm">
                  <q-chip dense :color="statusColor(ke.status)" text-color="white" size="sm">
                    {{ t(`knownErrors.statuses.${ke.status}`) }}
                  </q-chip>
                  <q-btn flat dense round icon="edit" @click.stop="openEdit(ke)">
                    <q-tooltip>{{ t('common.edit') }}</q-tooltip>
                  </q-btn>
                  <q-btn flat dense round icon="delete" color="negative" @click.stop="onDelete(ke)">
                    <q-tooltip>{{ t('common.delete') }}</q-tooltip>
                  </q-btn>
                </div>
              </q-item-section>
            </q-item>

            <!-- Expanded detail -->
            <q-slide-transition>
              <div v-if="expandedId === ke.id" class="q-pa-md bg-grey-1">
                <div class="row q-col-gutter-md">
                  <div class="col-12 col-md-6">
                    <div class="text-subtitle2 text-weight-bold q-mb-xs">{{ t('problems.fields.rootCause') }}</div>
                    <div style="white-space: pre-wrap;">{{ ke.root_cause || '-' }}</div>
                  </div>
                  <div class="col-12 col-md-6">
                    <div class="text-subtitle2 text-weight-bold q-mb-xs">{{ t('problems.fields.workaround') }}</div>
                    <div style="white-space: pre-wrap;">{{ ke.workaround || '-' }}</div>
                  </div>
                </div>
                <div v-if="ke.problem" class="q-mt-md">
                  <div class="text-caption text-grey">{{ t('knownErrors.relatedProblem') }}: {{ ke.problem.title }}</div>
                </div>
              </div>
            </q-slide-transition>
          </template>
        </template>

        <div v-else-if="!loading" class="text-center q-pa-xl text-grey">
          <q-icon name="warning" size="48px" class="q-mb-sm" />
          <div>{{ t('knownErrors.noKnownErrors') }}</div>
        </div>
      </q-list>

      <div class="row items-center justify-end q-pa-sm">
        <q-pagination
          v-model="currentPage"
          :max="totalPages"
          :max-pages="7"
          direction-links boundary-links
          @update:model-value="loadKnownErrors"
        />
      </div>
    </q-card>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 550px;">
        <q-card-section>
          <div class="text-h6">{{ editingId ? t('knownErrors.edit') : t('knownErrors.create') }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="form.title"
            :label="t('knownErrors.fields.title')"
            outlined
            :rules="[(v: string) => !!v || t('common.required')]"
            class="q-mb-md"
          />
          <q-input
            v-model="form.description"
            :label="t('knownErrors.fields.description')"
            type="textarea" outlined autogrow
            :rules="[(v: string) => !!v || t('common.required')]"
            class="q-mb-md"
          />
          <q-input
            v-model="form.root_cause"
            :label="t('problems.fields.rootCause')"
            type="textarea" outlined autogrow
            class="q-mb-md"
          />
          <q-input
            v-model="form.workaround"
            :label="t('problems.fields.workaround')"
            type="textarea" outlined autogrow
            class="q-mb-md"
          />
          <q-select
            v-model="form.status"
            :options="statusOptions"
            :label="t('common.status')"
            emit-value map-options outlined
            class="q-mb-md"
          />
          <q-select
            v-model="form.category_id"
            :options="categories.map(c => ({ label: c.name, value: c.id }))"
            :label="t('common.category')"
            emit-value map-options outlined clearable
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('common.save')" :loading="dialogSaving" @click="onSaveDialog" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
