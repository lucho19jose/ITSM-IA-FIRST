<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePortalStore } from '@/stores/portal'

const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string
const searchQuery = ref('')

const isAuth = computed(() => portal.isAuthenticated)

function onSearch() {
  if (!searchQuery.value.trim()) return
  router.push({ path: `/portal/${tenantSlug}/kb`, query: { search: searchQuery.value.trim() } })
  searchQuery.value = ''
}

function goTo(path: string, requiresAuth = false) {
  if (requiresAuth && !isAuth.value) {
    router.push({ path: `/portal/${tenantSlug}/login`, query: { redirect: `/portal/${tenantSlug}${path}` } })
    return
  }
  router.push(`/portal/${tenantSlug}${path}`)
}
</script>

<template>
  <q-page>
    <!-- Hero section -->
    <div class="portal-hero bg-primary">
      <div class="portal-hero-content">
        <h1 class="portal-hero-title text-white">Hola, ¿como podemos ayudarle?</h1>
        <q-input
          v-model="searchQuery"
          placeholder="Buscar soluciones, servicios y tickets"
          outlined rounded
          bg-color="white"
          input-class="text-dark"
          class="portal-search"
          @keyup.enter="onSearch"
        >
          <template v-slot:prepend>
            <q-icon name="search" size="24px" color="grey-6" />
          </template>
        </q-input>
      </div>
    </div>

    <!-- Action cards -->
    <div class="portal-cards-section">
      <div class="portal-cards-grid">
        <!-- My tickets (auth only) -->
        <div v-if="isAuth" class="portal-card" @click="goTo('/tickets')">
          <div class="portal-card-inner">
            <div class="portal-card-icon">
              <q-icon name="assignment_turned_in" size="48px" color="primary" />
            </div>
            <div class="portal-card-text">
              <div class="text-weight-bold text-body1">Complete los elementos de accion</div>
              <div class="text-grey-7 text-body2">Consulte las aprobaciones y otros elementos que esperan su respuesta</div>
            </div>
          </div>
        </div>

        <!-- KB articles (public) -->
        <div class="portal-card" @click="goTo('/kb')">
          <div class="portal-card-inner">
            <div class="portal-card-icon">
              <q-icon name="lightbulb" size="48px" color="teal" />
            </div>
            <div class="portal-card-text">
              <div class="text-weight-bold text-body1">Examinar articulos de ayuda</div>
              <div class="text-grey-7 text-body2">Busque las politicas o lea las preguntas frecuentes para solucionar problemas por su cuenta</div>
            </div>
          </div>
        </div>

        <!-- Report a problem -->
        <div class="portal-card" @click="goTo('/tickets/create', true)">
          <div class="portal-card-inner">
            <div class="portal-card-icon">
              <q-icon name="add_circle" size="48px" color="blue-grey" />
            </div>
            <div class="portal-card-text">
              <div class="text-weight-bold text-body1">Informar sobre un problema</div>
              <div class="text-grey-7 text-body2">¿Tiene problemas? Pongase en contacto con el equipo de soporte</div>
            </div>
          </div>
        </div>

        <!-- Service catalog (auth only) -->
        <div v-if="isAuth" class="portal-card" @click="goTo('/catalog')">
          <div class="portal-card-inner">
            <div class="portal-card-icon">
              <q-icon name="shopping_cart" size="48px" color="amber-8" />
            </div>
            <div class="portal-card-text">
              <div class="text-weight-bold text-body1">Solicitar un servicio</div>
              <div class="text-grey-7 text-body2">Explore la lista de servicios ofrecidos y envie una solicitud</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<style scoped>
.portal-hero {
  padding: 60px 24px 48px;
  text-align: center;
}
.portal-hero-content {
  max-width: 700px;
  margin: 0 auto;
}
.portal-hero-title {
  font-size: 2rem;
  font-weight: 700;
  margin: 0 0 24px 0;
}
.portal-search {
  max-width: 600px;
  margin: 0 auto;
}
.portal-search :deep(.q-field__control) {
  height: 48px;
  min-height: 48px;
}
.portal-search :deep(.q-field__marginal) {
  height: 48px;
}

.portal-cards-section {
  background: #f0f0f0;
  padding: 48px 24px;
  min-height: 400px;
}
.portal-cards-grid {
  max-width: 1000px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}
.portal-card {
  background: white;
  border-radius: 8px;
  cursor: pointer;
  transition: box-shadow 0.2s;
  border: 1px solid #e0e0e0;
}
.portal-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
.portal-card-inner {
  display: flex;
  align-items: flex-start;
  padding: 28px 24px;
  gap: 20px;
}
.portal-card-icon {
  flex-shrink: 0;
}
.portal-card-text {
  flex: 1;
}

/* Dark mode */
.body--dark .portal-cards-section {
  background: #1d1d1d;
}
.body--dark .portal-card {
  background: #2d2d2d;
  border-color: #404040;
}

@media (max-width: 768px) {
  .portal-cards-grid {
    grid-template-columns: 1fr;
  }
  .portal-hero-title {
    font-size: 1.5rem;
  }
}
</style>
