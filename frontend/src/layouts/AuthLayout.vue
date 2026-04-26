<script setup lang="ts">
import { onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'

const tenantStore = useTenantStore()

onMounted(() => {
  tenantStore.fetchTenantInfo()
})
</script>

<template>
  <q-layout view="hHh lpR fFf">
    <q-page-container>
      <q-page class="flex flex-center" style="background: linear-gradient(135deg, #1976D2 0%, #26A69A 100%)">
        <div class="column items-center q-pa-md" style="width: 100%; max-width: 450px">
          <div class="text-h4 text-white q-mb-sm text-weight-bold">Chuymadesk</div>
          <div v-if="tenantStore.tenantName" class="text-subtitle1 text-white q-mb-lg" style="opacity: 0.9">
            {{ tenantStore.tenantName }}
          </div>
          <div v-else class="q-mb-lg"></div>
          <q-card class="full-width" style="border-radius: 12px">
            <q-card-section>
              <router-view />
            </q-card-section>
          </q-card>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>
