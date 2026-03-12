<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPortalKbCategories, getPortalKbArticles } from '@/api/portal'
import type { KbCategory, KbArticle } from '@/types'

const route = useRoute()
const router = useRouter()
const tenantSlug = route.params.tenantSlug as string

const categories = ref<KbCategory[]>([])
const articles = ref<KbArticle[]>([])
const loading = ref(true)
const searchQuery = ref((route.query.search as string) || '')
const selectedCategory = ref<number | null>(null)

let debounceTimer: ReturnType<typeof setTimeout>

onMounted(async () => {
  try {
    const catRes = await getPortalKbCategories(tenantSlug)
    categories.value = catRes.data
    await fetchArticles()
  } finally {
    loading.value = false
  }
})

watch(searchQuery, () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchArticles(), 400)
})

watch(selectedCategory, () => fetchArticles())

async function fetchArticles() {
  loading.value = true
  try {
    const params: Record<string, any> = { per_page: 20 }
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    if (selectedCategory.value) params.category_id = selectedCategory.value
    const res = await getPortalKbArticles(tenantSlug, params)
    articles.value = res.data
  } finally {
    loading.value = false
  }
}

function viewArticle(id: number) {
  router.push(`/portal/${tenantSlug}/kb/${id}`)
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}
</script>

<template>
  <q-page class="portal-page">
    <!-- Hero search -->
    <div class="portal-kb-hero bg-primary">
      <div class="portal-kb-hero-content">
        <h2 class="text-white text-weight-bold q-mb-md" style="margin: 0;">Base de Conocimiento</h2>
        <q-input
          v-model="searchQuery"
          placeholder="Buscar articulos..."
          outlined rounded
          bg-color="white"
          input-class="text-dark"
          class="portal-kb-search"
        >
          <template v-slot:prepend>
            <q-icon name="search" size="22px" color="grey-6" />
          </template>
          <template v-slot:append v-if="searchQuery">
            <q-icon name="close" class="cursor-pointer" @click="searchQuery = ''" />
          </template>
        </q-input>
      </div>
    </div>

    <div class="portal-container">
      <!-- Category chips -->
      <div v-if="categories.length" class="q-mb-lg">
        <q-chip
          :color="selectedCategory === null ? 'primary' : undefined"
          :text-color="selectedCategory === null ? 'white' : undefined"
          clickable
          @click="selectedCategory = null"
        >
          Todos
        </q-chip>
        <q-chip
          v-for="cat in categories"
          :key="cat.id"
          :color="selectedCategory === cat.id ? 'primary' : undefined"
          :text-color="selectedCategory === cat.id ? 'white' : undefined"
          clickable
          @click="selectedCategory = cat.id"
        >
          <q-icon v-if="cat.icon" :name="cat.icon" class="q-mr-xs" />
          {{ cat.name }}
          <q-badge v-if="cat.articles_count" color="grey-4" text-color="grey-8" class="q-ml-xs">
            {{ cat.articles_count }}
          </q-badge>
        </q-chip>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center q-pa-xl">
        <q-spinner size="40px" color="primary" />
      </div>

      <!-- Empty -->
      <div v-else-if="articles.length === 0" class="text-center q-pa-xl">
        <q-icon name="article" size="64px" color="grey-4" />
        <div class="text-h6 text-grey-6 q-mt-md">No se encontraron articulos</div>
      </div>

      <!-- Article list -->
      <div v-else class="row q-col-gutter-md">
        <div v-for="article in articles" :key="article.id" class="col-12 col-sm-6">
          <q-card class="portal-kb-card cursor-pointer" @click="viewArticle(article.id)">
            <q-card-section>
              <q-item-label class="text-caption text-primary q-mb-xs" v-if="article.category">
                {{ article.category.name }}
              </q-item-label>
              <q-item-label class="text-weight-bold text-body1">{{ article.title }}</q-item-label>
              <q-item-label v-if="article.excerpt" class="text-grey-7 q-mt-xs text-body2" lines="2">
                {{ article.excerpt }}
              </q-item-label>
              <q-item-label caption class="q-mt-sm">
                <q-icon name="visibility" size="14px" class="q-mr-xs" />
                {{ article.views_count }} vistas
                &middot; {{ formatDate(article.created_at) }}
              </q-item-label>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-kb-hero {
  padding: 40px 24px 32px;
  text-align: center;
}
.portal-kb-hero-content {
  max-width: 600px;
  margin: 0 auto;
}
.portal-kb-search :deep(.q-field__control) {
  height: 44px;
  min-height: 44px;
}
.portal-kb-search :deep(.q-field__marginal) {
  height: 44px;
}
.portal-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 32px 24px;
}
.portal-kb-card {
  border-radius: 8px;
  transition: box-shadow 0.2s;
}
.portal-kb-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
.body--dark .portal-page {
  background: #121212;
}
</style>
