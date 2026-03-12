<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Notify } from 'quasar'
import { getKbArticle, markHelpful } from '@/api/kb'
import type { KbArticle } from '@/types'

const props = defineProps<{ id: string }>()
const { t } = useI18n()
const loading = ref(true)
const article = ref<KbArticle | null>(null)
const voted = ref(false)

onMounted(async () => {
  try {
    const res = await getKbArticle(Number(props.id))
    article.value = res.data
  } catch {
    /* article not found — handled by template */
  } finally {
    loading.value = false
  }
})

async function onHelpful(helpful: boolean) {
  if (!article.value || voted.value) return
  try {
    await markHelpful(article.value.id, helpful)
    voted.value = true
    Notify.create({ type: 'positive', message: 'Gracias por tu feedback' })
  } catch {
    /* handled */
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-PE', { day: '2-digit', month: 'long', year: 'numeric' })
}
</script>

<template>
  <q-page padding>
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <template v-else-if="article">
      <q-btn flat icon="arrow_back" to="/kb" class="q-mb-md" label="Volver" />

      <q-card flat bordered style="max-width: 900px; margin: 0 auto">
        <q-card-section>
          <div class="text-h5 q-mb-sm">{{ article.title }}</div>
          <div class="text-caption text-grey q-mb-md">
            Por {{ article.author?.name }} &middot; {{ formatDate(article.created_at) }} &middot; {{ article.views_count }} vistas
          </div>
          <q-separator class="q-mb-md" />
          <div v-html="article.content" class="kb-content"></div>
        </q-card-section>

        <q-separator />

        <q-card-section class="text-center">
          <div class="text-subtitle2 q-mb-sm">{{ t('kb.helpful') }}</div>
          <div v-if="!voted" class="q-gutter-sm">
            <q-btn outline color="positive" icon="thumb_up" :label="t('kb.yes')" @click="onHelpful(true)" />
            <q-btn outline color="negative" icon="thumb_down" :label="t('kb.no')" @click="onHelpful(false)" />
          </div>
          <div v-else class="text-positive">Gracias por tu feedback</div>
        </q-card-section>
      </q-card>
    </template>

    <div v-else class="text-center text-grey q-pa-xl">
      Articulo no encontrado
    </div>
  </q-page>
</template>

<style scoped>
.kb-content :deep(h2) { margin-top: 1.5rem; margin-bottom: 0.5rem; }
.kb-content :deep(h3) { margin-top: 1rem; margin-bottom: 0.5rem; }
.kb-content :deep(ol), .kb-content :deep(ul) { padding-left: 1.5rem; }
.kb-content :deep(code) { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
</style>
