<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { getKbCategories, getKbArticle, createKbArticle, updateKbArticle } from '@/api/kb'
import type { KbCategory } from '@/types'

const props = defineProps<{ id?: string }>()
const router = useRouter()
const loading = ref(false)
const categories = ref<KbCategory[]>([])

const form = ref({
  title: '',
  content: '',
  excerpt: '',
  category_id: undefined as number | undefined,
  status: 'draft' as 'draft' | 'published' | 'archived',
  is_public: true,
})

onMounted(async () => {
  const catRes = await getKbCategories()
  categories.value = catRes.data

  if (props.id) {
    const artRes = await getKbArticle(Number(props.id))
    const a = artRes.data
    form.value = {
      title: a.title,
      content: a.content,
      excerpt: a.excerpt || '',
      category_id: a.category_id ?? undefined,
      status: a.status,
      is_public: a.is_public,
    }
  }
})

async function onSubmit() {
  loading.value = true
  try {
    if (props.id) {
      await updateKbArticle(Number(props.id), form.value)
      Notify.create({ type: 'positive', message: 'Articulo actualizado' })
    } else {
      await createKbArticle(form.value)
      Notify.create({ type: 'positive', message: 'Articulo creado' })
    }
    router.push('/kb')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <q-btn flat icon="arrow_back" to="/kb" class="q-mr-sm" />
      <div class="text-h5">{{ props.id ? 'Editar' : 'Nuevo' }} Articulo</div>
    </div>

    <q-card flat bordered style="max-width: 900px">
      <q-card-section>
        <q-form @submit.prevent="onSubmit" class="q-gutter-md">
          <q-input v-model="form.title" label="Titulo *" outlined :rules="[val => !!val || 'Requerido']" />
          <q-input v-model="form.excerpt" label="Resumen" outlined type="textarea" autogrow />

          <q-editor
            v-model="form.content"
            min-height="300px"
            :toolbar="[
              ['bold','italic','underline','strike'],
              ['hr','link'],
              ['unordered','ordered'],
              [{ label: 'Heading', list: 'no-icons', options: ['p','h2','h3','h4'] }],
              ['undo','redo'],
              ['viewsource'],
            ]"
          />

          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-4">
              <q-select
                v-model="form.category_id"
                :options="categories.map(c => ({ label: c.name, value: c.id }))"
                label="Categoria *"
                outlined emit-value map-options
                :rules="[val => !!val || 'Requerido']"
              />
            </div>
            <div class="col-12 col-sm-4">
              <q-select
                v-model="form.status"
                :options="[
                  { label: 'Borrador', value: 'draft' },
                  { label: 'Publicado', value: 'published' },
                  { label: 'Archivado', value: 'archived' },
                ]"
                label="Estado"
                outlined emit-value map-options
              />
            </div>
            <div class="col-12 col-sm-4 flex items-center">
              <q-toggle v-model="form.is_public" label="Articulo publico" />
            </div>
          </div>

          <div class="row justify-end q-gutter-sm">
            <q-btn flat label="Cancelar" to="/kb" />
            <q-btn type="submit" color="primary" :label="props.id ? 'Actualizar' : 'Crear'" :loading="loading" />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </q-page>
</template>
