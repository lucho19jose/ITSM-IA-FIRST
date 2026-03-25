<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify, useQuasar } from 'quasar'
import { getCannedResponses, createCannedResponse, updateCannedResponse, deleteCannedResponse } from '@/api/cannedResponses'
import { useAuthStore } from '@/stores/auth'
import type { CannedResponse } from '@/types'

const { t } = useI18n()
const $q = useQuasar()
const auth = useAuthStore()

const loading = ref(true)
const responses = ref<CannedResponse[]>([])
const showDialog = ref(false)
const editingResponse = ref<CannedResponse | null>(null)
const saving = ref(false)

const filterCategory = ref<string | null>(null)
const filterVisibility = ref<string | null>(null)

const form = ref({
  title: '',
  content: '',
  category: '',
  visibility: 'personal' as string,
  shortcut: '',
})

const visibilityOptions = [
  { label: t('cannedResponses.personal'), value: 'personal' },
  { label: t('cannedResponses.team'), value: 'team' },
  { label: t('cannedResponses.global'), value: 'global' },
]

const columns = [
  { name: 'title', label: t('cannedResponses.title'), field: 'title', align: 'left' as const, sortable: true },
  { name: 'category', label: t('common.category'), field: 'category', align: 'left' as const, sortable: true },
  { name: 'visibility', label: t('cannedResponses.visibility'), field: 'visibility', align: 'center' as const, sortable: true },
  { name: 'shortcut', label: t('cannedResponses.shortcut'), field: 'shortcut', align: 'left' as const },
  { name: 'usage_count', label: t('cannedResponses.usageCount'), field: 'usage_count', align: 'center' as const, sortable: true },
  { name: 'actions', label: t('common.actions'), field: 'id', align: 'center' as const },
]

const categoryOptions = computed(() => {
  const cats = responses.value
    .map(r => r.category)
    .filter((c): c is string => !!c)
  return [...new Set(cats)]
})

const filteredResponses = computed(() => {
  let result = responses.value
  if (filterCategory.value) {
    result = result.filter(r => r.category === filterCategory.value)
  }
  if (filterVisibility.value) {
    result = result.filter(r => r.visibility === filterVisibility.value)
  }
  return result
})

onMounted(async () => {
  try {
    const res = await getCannedResponses()
    responses.value = res.data
  } finally {
    loading.value = false
  }
})

function openCreate() {
  editingResponse.value = null
  form.value = { title: '', content: '', category: '', visibility: 'personal', shortcut: '' }
  showDialog.value = true
}

function openEdit(response: CannedResponse) {
  editingResponse.value = response
  form.value = {
    title: response.title,
    content: response.content,
    category: response.category || '',
    visibility: response.visibility,
    shortcut: response.shortcut || '',
  }
  showDialog.value = true
}

async function onSubmit() {
  saving.value = true
  try {
    const data: any = {
      title: form.value.title,
      content: form.value.content,
      category: form.value.category || null,
      visibility: form.value.visibility,
      shortcut: form.value.shortcut || null,
    }

    if (editingResponse.value) {
      const res = await updateCannedResponse(editingResponse.value.id, data)
      const idx = responses.value.findIndex(r => r.id === editingResponse.value!.id)
      if (idx >= 0) responses.value[idx] = res.data
      Notify.create({ type: 'positive', message: t('cannedResponses.updated') })
    } else {
      const res = await createCannedResponse(data)
      responses.value.push(res.data)
      Notify.create({ type: 'positive', message: t('cannedResponses.created') })
    }
    showDialog.value = false
  } catch {
    /* handled by interceptor */
  } finally {
    saving.value = false
  }
}

async function onDelete(response: CannedResponse) {
  $q.dialog({
    title: t('common.confirm'),
    message: t('cannedResponses.confirmDelete'),
    cancel: t('common.cancel'),
    ok: { label: t('common.delete'), color: 'negative' },
  }).onOk(async () => {
    try {
      await deleteCannedResponse(response.id)
      responses.value = responses.value.filter(r => r.id !== response.id)
      Notify.create({ type: 'positive', message: t('cannedResponses.deleted') })
    } catch {
      /* handled */
    }
  })
}

function getVisibilityBadge(visibility: string): { color: string; label: string } {
  const map: Record<string, { color: string; label: string }> = {
    personal: { color: 'blue-grey', label: t('cannedResponses.personal') },
    team: { color: 'primary', label: t('cannedResponses.team') },
    global: { color: 'green', label: t('cannedResponses.global') },
  }
  return map[visibility] || { color: 'grey', label: visibility }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">{{ t('cannedResponses.pageTitle') }}</div>
      <q-space />
      <q-select
        v-model="filterCategory"
        :options="categoryOptions"
        :label="t('common.category')"
        clearable dense outlined
        class="q-mr-sm"
        style="min-width: 150px;"
      />
      <q-select
        v-model="filterVisibility"
        :options="visibilityOptions"
        :label="t('cannedResponses.visibility')"
        clearable dense outlined emit-value map-options
        class="q-mr-sm"
        style="min-width: 150px;"
      />
      <q-btn color="primary" icon="add" :label="t('cannedResponses.new')" @click="openCreate" />
    </div>

    <q-table
      flat bordered
      :rows="filteredResponses"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :rows-per-page-options="[10, 25, 50]"
    >
      <template v-slot:body-cell-category="props">
        <q-td :props="props">
          <q-chip v-if="props.row.category" size="sm" color="blue-2" text-color="blue-9" dense>
            {{ props.row.category }}
          </q-chip>
          <span v-else class="text-grey-5">-</span>
        </q-td>
      </template>
      <template v-slot:body-cell-visibility="props">
        <q-td :props="props">
          <q-badge :color="getVisibilityBadge(props.row.visibility).color">
            {{ getVisibilityBadge(props.row.visibility).label }}
          </q-badge>
        </q-td>
      </template>
      <template v-slot:body-cell-shortcut="props">
        <q-td :props="props">
          <code v-if="props.row.shortcut" class="text-caption">{{ props.row.shortcut }}</code>
          <span v-else class="text-grey-5">-</span>
        </q-td>
      </template>
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat size="sm" icon="edit" @click="openEdit(props.row)" />
          <q-btn flat size="sm" icon="delete" color="negative" @click="onDelete(props.row)" />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="showDialog" persistent>
      <q-card style="width: 800px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">
            {{ editingResponse ? t('cannedResponses.edit') : t('cannedResponses.new') }}
          </div>
        </q-card-section>
        <q-card-section>
          <q-form @submit.prevent="onSubmit" class="q-gutter-md">
            <q-input
              v-model="form.title"
              :label="t('cannedResponses.title')"
              outlined
              :rules="[val => !!val || 'Requerido']"
            />
            <q-editor
              v-model="form.content"
              min-height="150px"
              :toolbar="[
                ['bold', 'italic', 'underline', 'strike'],
                ['unordered_list', 'ordered_list'],
                ['link', 'code'],
                ['undo', 'redo'],
              ]"
              :placeholder="t('cannedResponses.contentPlaceholder')"
            />
            <div class="row q-gutter-md">
              <q-input
                v-model="form.category"
                :label="t('common.category')"
                outlined dense
                class="col"
              />
              <q-select
                v-model="form.visibility"
                :options="visibilityOptions"
                :label="t('cannedResponses.visibility')"
                outlined dense emit-value map-options
                class="col"
              />
              <q-input
                v-model="form.shortcut"
                :label="t('cannedResponses.shortcut')"
                outlined dense
                class="col"
                :hint="t('cannedResponses.shortcutHint')"
              />
            </div>
            <div class="row justify-end q-gutter-sm">
              <q-btn flat :label="t('common.cancel')" v-close-popup />
              <q-btn
                type="submit"
                color="primary"
                :label="editingResponse ? t('common.save') : t('common.create')"
                :loading="saving"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>
