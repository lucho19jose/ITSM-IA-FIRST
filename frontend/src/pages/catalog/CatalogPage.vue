<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import { getCatalogItems, requestCatalogItem } from '@/api/catalog'
import type { ServiceCatalogItem } from '@/types'

const { t } = useI18n()
const loading = ref(true)
const items = ref<ServiceCatalogItem[]>([])
const requestDialog = ref(false)
const selectedItem = ref<ServiceCatalogItem | null>(null)
const requestDescription = ref('')
const submitting = ref(false)

onMounted(async () => {
  try {
    const res = await getCatalogItems()
    items.value = res.data
  } finally {
    loading.value = false
  }
})

function openRequest(item: ServiceCatalogItem) {
  selectedItem.value = item
  requestDescription.value = ''
  requestDialog.value = true
}

async function submitRequest() {
  if (!selectedItem.value) return
  submitting.value = true
  try {
    await requestCatalogItem(selectedItem.value.id, { description: requestDescription.value })
    Notify.create({ type: 'positive', message: 'Solicitud creada exitosamente' })
    requestDialog.value = false
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">{{ t('catalog.title') }}</div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <div v-else-if="items.length === 0" class="text-center text-grey q-pa-xl">
      No hay servicios disponibles
    </div>

    <div v-else class="row q-col-gutter-md">
      <div class="col-12 col-sm-6 col-md-4" v-for="item in items" :key="item.id">
        <q-card flat bordered class="full-height">
          <q-card-section>
            <div class="row items-center q-mb-sm">
              <q-icon :name="item.icon || 'miscellaneous_services'" size="32px" color="primary" class="q-mr-sm" />
              <div class="text-subtitle1 text-weight-medium">{{ item.name }}</div>
            </div>
            <div class="text-body2 text-grey">{{ item.description }}</div>
            <div class="q-mt-sm">
              <q-badge v-if="item.category" outline color="grey">{{ item.category }}</q-badge>
              <q-badge v-if="item.approval_required" outline color="orange" class="q-ml-xs">{{ t('catalog.approvalRequired') }}</q-badge>
            </div>
            <div v-if="item.estimated_days" class="text-caption text-grey q-mt-xs">
              {{ item.estimated_days }} {{ t('catalog.estimatedDays') }}
            </div>
          </q-card-section>
          <q-card-actions>
            <q-btn flat color="primary" :label="t('catalog.request')" @click="openRequest(item)" />
          </q-card-actions>
        </q-card>
      </div>
    </div>

    <!-- Request Dialog -->
    <q-dialog v-model="requestDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Solicitar: {{ selectedItem?.name }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="requestDescription"
            label="Descripcion adicional"
            outlined
            type="textarea"
            autogrow
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn color="primary" label="Enviar solicitud" :loading="submitting" @click="submitRequest" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
