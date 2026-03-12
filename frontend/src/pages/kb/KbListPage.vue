<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getKbCategories, getKbArticles } from '@/api/kb'
import type { KbCategory, KbArticle } from '@/types'

const { t } = useI18n()
const loading = ref(true)
const categories = ref<KbCategory[]>([])
const articles = ref<KbArticle[]>([])
const search = ref('')
const selectedCategory = ref<number | null>(null)

onMounted(async () => {
  try {
    const [catRes, artRes] = await Promise.all([
      getKbCategories(),
      getKbArticles({ per_page: 20 }),
    ])
    categories.value = catRes.data
    articles.value = artRes.data
  } finally {
    loading.value = false
  }
})

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(loadArticles, 400)
})

watch(selectedCategory, loadArticles)

async function loadArticles() {
  loading.value = true
  try {
    const params: Record<string, any> = { per_page: 20 }
    if (search.value) params.search = search.value
    if (selectedCategory.value) params.category_id = selectedCategory.value
    const res = await getKbArticles(params)
    articles.value = res.data
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page padding>
    <div class="text-h5 q-mb-md">{{ t('kb.title') }}</div>

    <!-- Search -->
    <q-input v-model="search" :placeholder="t('kb.search')" outlined class="q-mb-md" clearable>
      <template v-slot:prepend><q-icon name="search" /></template>
    </q-input>

    <!-- Categories -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-6 col-sm-4 col-md-2" v-for="cat in categories" :key="cat.id">
        <q-card
          flat bordered
          :class="{ 'bg-primary text-white': selectedCategory === cat.id }"
          class="cursor-pointer text-center q-pa-sm"
          @click="selectedCategory = selectedCategory === cat.id ? null : cat.id"
        >
          <q-icon :name="cat.icon || 'folder'" size="32px" class="q-mb-xs" />
          <div class="text-caption text-weight-medium">{{ cat.name }}</div>
          <div class="text-caption">{{ cat.articles_count || 0 }} articulos</div>
        </q-card>
      </div>
    </div>

    <!-- Articles -->
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>
    <div v-else-if="articles.length === 0" class="text-center text-grey q-pa-xl">
      {{ t('common.noResults') }}
    </div>
    <div v-else class="row q-col-gutter-md">
      <div class="col-12 col-md-6" v-for="article in articles" :key="article.id">
        <q-card flat bordered class="cursor-pointer" @click="$router.push(`/kb/articles/${article.id}`)">
          <q-card-section>
            <div class="text-subtitle1 text-weight-medium">{{ article.title }}</div>
            <div class="text-body2 text-grey q-mt-xs">{{ article.excerpt }}</div>
            <div class="row items-center q-mt-sm text-caption text-grey">
              <q-icon name="visibility" size="14px" class="q-mr-xs" />
              {{ article.views_count }} {{ t('kb.views') }}
              <q-space />
              <q-badge v-if="article.category" outline color="primary">{{ article.category.name }}</q-badge>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>
