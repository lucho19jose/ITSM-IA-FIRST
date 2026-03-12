<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPortalKbArticle } from '@/api/portal'
import { markHelpful } from '@/api/kb'
import type { KbArticle } from '@/types'
import { usePortalStore } from '@/stores/portal'

const props = defineProps<{ id: string | number }>()
const route = useRoute()
const router = useRouter()
const portal = usePortalStore()
const tenantSlug = route.params.tenantSlug as string

const article = ref<KbArticle | null>(null)
const loading = ref(true)
const voted = ref<boolean | null>(null)

onMounted(async () => {
  try {
    const res = await getPortalKbArticle(tenantSlug, Number(props.id))
    article.value = res.data
  } catch {
    router.push(`/portal/${tenantSlug}/kb`)
  } finally {
    loading.value = false
  }
})

async function vote(helpful: boolean) {
  if (!article.value || voted.value !== null) return
  if (!portal.isAuthenticated) return
  try {
    await markHelpful(article.value.id, helpful)
    voted.value = helpful
    if (helpful) article.value.helpful_count++
    else article.value.not_helpful_count++
  } catch {
    // ignore
  }
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-PE', {
    day: '2-digit', month: 'long', year: 'numeric',
  })
}
</script>

<template>
  <q-page class="portal-page">
    <div class="portal-container">
      <!-- Loading -->
      <div v-if="loading" class="text-center q-pa-xl">
        <q-spinner size="40px" color="primary" />
      </div>

      <template v-else-if="article">
        <!-- Breadcrumb -->
        <div class="row items-center q-mb-md q-gutter-sm">
          <router-link :to="`/portal/${tenantSlug}/kb`" class="text-primary" style="text-decoration: none;">
            Base de Conocimiento
          </router-link>
          <q-icon name="chevron_right" size="18px" color="grey-6" />
          <span v-if="article.category" class="text-grey-7">{{ article.category.name }}</span>
        </div>

        <q-card>
          <q-card-section>
            <h1 class="text-h5 text-weight-bold" style="margin: 0;">{{ article.title }}</h1>
            <div class="text-caption text-grey-7 q-mt-sm">
              <span v-if="article.author">Por {{ article.author.name }} &middot; </span>
              {{ formatDate(article.published_at || article.created_at) }}
              &middot;
              <q-icon name="visibility" size="14px" class="q-mr-xs" />
              {{ article.views_count }} vistas
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <div class="kb-article-content" v-html="article.content"></div>
          </q-card-section>

          <q-separator />

          <!-- Helpful vote -->
          <q-card-section class="text-center">
            <div class="text-body2 q-mb-sm">¿Te fue util este articulo?</div>
            <div class="row justify-center q-gutter-sm">
              <q-btn
                :color="voted === true ? 'green' : 'grey-4'"
                :text-color="voted === true ? 'white' : 'grey-8'"
                icon="thumb_up"
                :label="`Si (${article.helpful_count})`"
                no-caps flat
                :disable="voted !== null || !portal.isAuthenticated"
                @click="vote(true)"
              />
              <q-btn
                :color="voted === false ? 'red' : 'grey-4'"
                :text-color="voted === false ? 'white' : 'grey-8'"
                icon="thumb_down"
                :label="`No (${article.not_helpful_count})`"
                no-caps flat
                :disable="voted !== null || !portal.isAuthenticated"
                @click="vote(false)"
              />
            </div>
            <div v-if="!portal.isAuthenticated" class="text-caption text-grey-6 q-mt-xs">
              <router-link :to="`/portal/${tenantSlug}/login`" class="text-primary">Inicia sesion</router-link> para votar
            </div>
          </q-card-section>
        </q-card>
      </template>
    </div>
  </q-page>
</template>

<style scoped>
.portal-page {
  background: #f5f5f5;
  min-height: calc(100vh - 50px);
}
.portal-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 32px 24px;
}
.kb-article-content :deep(h1),
.kb-article-content :deep(h2),
.kb-article-content :deep(h3) {
  margin-top: 1.5em;
  margin-bottom: 0.5em;
}
.kb-article-content :deep(p) {
  margin-bottom: 1em;
  line-height: 1.7;
}
.kb-article-content :deep(ul),
.kb-article-content :deep(ol) {
  margin-bottom: 1em;
  padding-left: 1.5em;
}
.kb-article-content :deep(code) {
  background: #f5f5f5;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.9em;
}
.body--dark .portal-page {
  background: #121212;
}
.body--dark .kb-article-content :deep(code) {
  background: #2d2d2d;
}
</style>
