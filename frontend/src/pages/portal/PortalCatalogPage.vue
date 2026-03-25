<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPortalCatalog } from '@/api/portal'
import { requestCatalogItem } from '@/api/catalog'
import { usePortalStore } from '@/stores/portal'
import type { ServiceCatalogItem } from '@/types'
import { Notify } from 'quasar'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const items = ref<ServiceCatalogItem[]>([])
const loading = ref(true)
const requestDialog = ref(false)
const selectedItem = ref<ServiceCatalogItem | null>(null)
const requestDescription = ref('')
const requesting = ref(false)

onMounted(async () => {
  try {
    const res = await getPortalCatalog(tenantSlug)
    items.value = res.data
  } finally {
    loading.value = false
  }
})

function openRequest(item: ServiceCatalogItem) {
  if (!portal.isAuthenticated) {
    router.push({ path: `/portal/${tenantSlug}/login`, query: { redirect: `/portal/${tenantSlug}/catalog` } })
    return
  }
  selectedItem.value = item
  requestDescription.value = ''
  requestDialog.value = true
}

async function submitRequest() {
  if (!selectedItem.value) return
  requesting.value = true
  try {
    await requestCatalogItem(selectedItem.value.id, { description: requestDescription.value })
    Notify.create({ type: 'positive', message: 'Solicitud enviada correctamente' })
    requestDialog.value = false
  } catch {
    // handled by interceptor
  } finally {
    requesting.value = false
  }
}
</script>

<template>
  <q-page class="portal-page">
    <!-- Hero -->
    <div class="portal-catalog-hero bg-primary">
      <div class="text-center">
        <h2 class="text-white text-weight-bold" style="margin: 0;">Catalogo de Servicios</h2>
        <p class="text-white" style="opacity: 0.85; margin: 8px 0 0;">Explore los servicios disponibles y envie una solicitud</p>
      </div>
    </div>

    <div class="portal-container">
      <!-- Loading -->
      <div v-if="loading" class="text-center q-pa-xl">
        <q-spinner size="40px" color="primary" />
      </div>

      <!-- Empty -->
      <div v-else-if="items.length === 0" class="text-center q-pa-xl">
        <q-icon name="storefront" size="64px" color="grey-4" />
        <div class="text-h6 text-grey-6 q-mt-md">No hay servicios disponibles</div>
      </div>

      <!-- Catalog grid -->
      <div v-else class="row q-col-gutter-md">
        <div v-for="item in items" :key="item.id" class="col-12 col-sm-6 col-md-4">
          <q-card class="portal-catalog-card full-height">
            <q-card-section class="text-center q-pb-none">
              <q-icon :name="item.icon || 'miscellaneous_services'" size="48px" color="primary" />
            </q-card-section>
            <q-card-section>
              <div class="text-weight-bold text-body1 text-center">{{ item.name }}</div>
              <div v-if="item.category" class="text-caption text-center text-grey-7 q-mt-xs">{{ item.category }}</div>
              <div class="text-body2 text-grey-7 q-mt-sm" style="line-height: 1.5;">{{ item.description }}</div>
              <div class="row justify-center q-mt-sm q-gutter-xs">
                <q-badge v-if="item.approval_required" color="orange" label="Requiere aprobacion" />
                <q-badge v-if="item.estimated_days" color="blue-grey" :label="`~${item.estimated_days} dias`" />
              </div>
            </q-card-section>
            <q-card-actions align="center">
              <q-btn
                color="primary"
                label="Solicitar"
                no-caps flat
                icon="shopping_cart"
                @click="openRequest(item)"
              />
            </q-card-actions>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Request dialog -->
    <q-dialog v-model="requestDialog">
      <q-card style="width: 400px; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">Solicitar: {{ selectedItem?.name }}</div>
        </q-card-section>
        <q-card-section>
          <q-input
            v-model="requestDescription"
            label="Descripcion de la solicitud (opcional)"
            type="textarea"
            outlined
            autogrow
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup no-caps />
          <q-btn color="primary" label="Enviar Solicitud" no-caps :loading="requesting" @click="submitRequest" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-catalog-hero {
  padding: 40px 24px;
}
.portal-container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 32px 24px;
}
.portal-catalog-card {
  border-radius: 8px;
  transition: box-shadow 0.2s;
}
.portal-catalog-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
.body--dark .portal-page {
  background: #121212;
}
</style>
