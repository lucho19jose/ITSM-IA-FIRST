<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const leftDrawerOpen = ref(true)

const menuItems = [
  { icon: 'dashboard', label: 'Dashboard', to: '/super-admin' },
  { icon: 'business', label: 'Tenants', to: '/super-admin/tenants' },
]

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <q-layout view="hHh LpR fFf">
    <q-header elevated class="bg-dark">
      <q-toolbar>
        <q-btn flat dense round icon="menu" @click="leftDrawerOpen = !leftDrawerOpen" />
        <q-toolbar-title>
          AutoService
          <q-badge color="red" class="q-ml-sm">Super Admin</q-badge>
        </q-toolbar-title>
        <q-space />
        <q-btn flat round icon="person">
          <q-menu>
            <q-list style="min-width: 200px">
              <q-item-label header>{{ auth.user?.name }}</q-item-label>
              <q-item-label caption class="q-px-md">{{ auth.user?.email }}</q-item-label>
              <q-separator />
              <q-item clickable v-close-popup @click="handleLogout">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>Cerrar Sesion</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" bordered :width="260" show-if-above class="bg-dark">
      <q-list dark>
        <q-item-label header class="text-white text-weight-bold">
          Plataforma
        </q-item-label>

        <q-item
          v-for="item in menuItems"
          :key="item.to"
          :to="item.to"
          clickable
          v-ripple
          dark
          active-class="bg-grey-9"
        >
          <q-item-section avatar>
            <q-icon :name="item.icon" color="white" />
          </q-item-section>
          <q-item-section class="text-white">{{ item.label }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>
