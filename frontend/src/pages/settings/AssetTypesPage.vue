<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuasar } from 'quasar'
import { getAssetTypes, createAssetType, updateAssetType, deleteAssetType } from '@/api/assetTypes'
import type { AssetType, AssetTypeField } from '@/types'

const { t } = useI18n()
const $q = useQuasar()

const loading = ref(false)
const types = ref<AssetType[]>([])
const showDialog = ref(false)
const editingType = ref<AssetType | null>(null)

const form = ref({
  name: '',
  icon: '',
  fields: [] as AssetTypeField[],
})

const fieldTypeOptions = [
  { label: 'Texto', value: 'text' },
  { label: 'Area de texto', value: 'textarea' },
  { label: 'Numero', value: 'number' },
  { label: 'Fecha', value: 'date' },
  { label: 'Seleccion', value: 'select' },
  { label: 'Checkbox', value: 'checkbox' },
  { label: 'URL', value: 'url' },
  { label: 'Email', value: 'email' },
]

async function loadTypes() {
  loading.value = true
  try {
    const res = await getAssetTypes()
    types.value = res.data
  } catch { /* ignore */ }
  finally { loading.value = false }
}

function openCreate() {
  editingType.value = null
  form.value = { name: '', icon: '', fields: [] }
  showDialog.value = true
}

function openEdit(type: AssetType) {
  editingType.value = type
  form.value = {
    name: type.name,
    icon: type.icon || '',
    fields: type.fields ? [...type.fields] : [],
  }
  showDialog.value = true
}

function addField() {
  form.value.fields.push({
    name: '',
    label: '',
    type: 'text',
    options: undefined,
    required: false,
  })
}

function removeField(index: number) {
  form.value.fields.splice(index, 1)
}

async function onSave() {
  if (!form.value.name) return

  // Auto-generate name from label if empty
  form.value.fields.forEach(f => {
    if (!f.name && f.label) {
      f.name = f.label.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '')
    }
  })

  try {
    if (editingType.value) {
      await updateAssetType(editingType.value.id, form.value)
      $q.notify({ type: 'positive', message: t('assets.typeUpdated') })
    } else {
      await createAssetType(form.value)
      $q.notify({ type: 'positive', message: t('assets.typeCreated') })
    }
    showDialog.value = false
    loadTypes()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al guardar tipo de activo' })
  }
}

async function onDelete(type: AssetType) {
  $q.dialog({
    title: t('common.confirm'),
    message: t('assets.confirmDeleteType', { name: type.name }),
    cancel: true,
  }).onOk(async () => {
    try {
      await deleteAssetType(type.id)
      $q.notify({ type: 'positive', message: t('assets.typeDeleted') })
      loadTypes()
    } catch (e: any) {
      $q.notify({
        type: 'negative',
        message: e?.response?.data?.message || 'Error al eliminar tipo de activo',
      })
    }
  })
}

onMounted(loadTypes)
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="col">
        <div class="text-h5 text-weight-bold">{{ t('assets.assetTypes') }}</div>
        <div class="text-caption text-grey">{{ t('assets.assetTypesSubtitle') }}</div>
      </div>
      <q-btn color="primary" icon="add" :label="t('assets.newType')" no-caps @click="openCreate" />
    </div>

    <q-linear-progress v-if="loading" indeterminate color="primary" />

    <div class="row q-col-gutter-md">
      <div v-for="type in types" :key="type.id" class="col-12 col-sm-6 col-md-4">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center q-gutter-sm">
              <q-icon :name="type.icon || 'devices'" size="28px" color="primary" />
              <div class="col">
                <div class="text-subtitle1 text-weight-bold">{{ type.name }}</div>
                <div class="text-caption text-grey">
                  {{ type.fields?.length || 0 }} {{ t('assets.customFieldsCount') }}
                  <span v-if="type.assets_count !== undefined"> | {{ type.assets_count }} {{ t('assets.assetsCount') }}</span>
                </div>
              </div>
              <q-btn flat round dense icon="more_vert" size="sm">
                <q-menu>
                  <q-list dense>
                    <q-item clickable v-close-popup @click="openEdit(type)">
                      <q-item-section side><q-icon name="edit" size="18px" /></q-item-section>
                      <q-item-section>{{ t('common.edit') }}</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="onDelete(type)" class="text-negative">
                      <q-item-section side><q-icon name="delete" size="18px" color="negative" /></q-item-section>
                      <q-item-section>{{ t('common.delete') }}</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
          </q-card-section>

          <q-separator v-if="type.fields?.length" />

          <q-card-section v-if="type.fields?.length" class="q-pa-sm">
            <q-chip v-for="field in type.fields" :key="field.name" dense size="sm" color="blue-1" text-color="blue-9">
              {{ field.label }} ({{ field.type }})
            </q-chip>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div v-if="!loading && !types.length" class="text-center q-pa-xl text-grey">
      <q-icon name="category" size="48px" class="q-mb-sm" />
      <div>{{ t('assets.noAssetTypes') }}</div>
    </div>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="width: 700px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">{{ editingType ? t('assets.editType') : t('assets.newType') }}</div>
        </q-card-section>

        <q-card-section class="q-gutter-sm">
          <q-input v-model="form.name" :label="t('assets.typeName') + ' *'" outlined dense />
          <q-input v-model="form.icon" :label="t('assets.typeIcon')" outlined dense hint="Material icon name, e.g. laptop, dns, print">
            <template v-slot:prepend>
              <q-icon :name="form.icon || 'devices'" />
            </template>
          </q-input>

          <q-separator class="q-my-md" />
          <div class="row items-center">
            <div class="col text-subtitle2">{{ t('assets.customFields') }}</div>
            <q-btn flat color="primary" icon="add" :label="t('assets.addField')" no-caps dense @click="addField" />
          </div>

          <div v-for="(field, idx) in form.fields" :key="idx" class="row q-col-gutter-sm items-center q-mt-xs">
            <div class="col-4">
              <q-input v-model="field.label" label="Label *" outlined dense />
            </div>
            <div class="col-3">
              <q-select
                v-model="field.type"
                :options="fieldTypeOptions"
                option-value="value"
                option-label="label"
                emit-value map-options
                label="Tipo"
                outlined dense
              />
            </div>
            <div class="col-3">
              <q-input
                v-if="field.type === 'select'"
                label="Opciones"
                outlined dense
                hint="Separadas por coma"
                :model-value="field.options?.join(', ') ?? ''"
                @update:model-value="(val: string | number | null) => field.options = String(val || '').split(',').map((s: string) => s.trim()).filter(Boolean)"
              />
              <q-checkbox v-else v-model="field.required" label="Requerido" dense />
            </div>
            <div class="col-1">
              <q-btn flat round dense icon="close" color="negative" size="sm" @click="removeField(idx)" />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn color="primary" :label="t('common.save')" :disable="!form.name" @click="onSave" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
