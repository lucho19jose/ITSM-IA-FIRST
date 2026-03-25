<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import {
  getProfile,
  updateProfile,
  changePassword,
  uploadAvatar,
  deleteAvatar,
} from '@/api/profile'

const { t } = useI18n()
const $q = useQuasar()
const auth = useAuthStore()

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(false)
const savingProfile = ref(false)
const savingPassword = ref(false)
const uploadingAvatar = ref(false)

const activeTab = ref('profile')

const editDialogOpen = ref(false)
const passwordDialogOpen = ref(false)

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

// ─── Form models ──────────────────────────────────────────────────────────────
const editForm = reactive({
  name: '',
  phone: '',
  work_phone: '',
  job_title: '',
  location: '',
  address: '',
  timezone: '',
  language: '',
  signature: '',
  time_format: '24h',
})

const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

// ─── Options ──────────────────────────────────────────────────────────────────
const timezoneOptions = [
  { label: 'America/Lima (UTC-5)', value: 'America/Lima' },
  { label: 'America/Bogota (UTC-5)', value: 'America/Bogota' },
  { label: 'America/Mexico_City (UTC-6)', value: 'America/Mexico_City' },
  { label: 'America/New_York (UTC-5)', value: 'America/New_York' },
  { label: 'America/Chicago (UTC-6)', value: 'America/Chicago' },
  { label: 'America/Denver (UTC-7)', value: 'America/Denver' },
  { label: 'America/Los_Angeles (UTC-8)', value: 'America/Los_Angeles' },
  { label: 'Europe/London (UTC+0)', value: 'Europe/London' },
  { label: 'Europe/Madrid (UTC+1)', value: 'Europe/Madrid' },
  { label: 'Europe/Paris (UTC+1)', value: 'Europe/Paris' },
  { label: 'Asia/Tokyo (UTC+9)', value: 'Asia/Tokyo' },
  { label: 'UTC', value: 'UTC' },
]

const languageOptions = [
  { label: 'Español', value: 'es' },
  { label: 'English', value: 'en' },
]

const timeFormatOptions = [
  { label: '12 horas (1:00 PM)', value: '12h' },
  { label: '24 horas (13:00)', value: '24h' },
]

// ─── Computed ─────────────────────────────────────────────────────────────────
const userInitial = computed(() =>
  auth.user?.name?.charAt(0)?.toUpperCase() ?? '?'
)

const timezoneLabel = computed(() => {
  const found = timezoneOptions.find((o) => o.value === auth.user?.timezone)
  return found ? found.label : auth.user?.timezone || '—'
})

const languageLabel = computed(() => {
  const found = languageOptions.find((o) => o.value === auth.user?.language)
  return found ? found.label : auth.user?.language || '—'
})

// ─── Load ─────────────────────────────────────────────────────────────────────
async function loadProfile() {
  loading.value = true
  try {
    const res = await getProfile()
    auth.user = res.data
  } catch {
    // silently use cached auth.user if available
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadProfile()
})

// ─── Edit dialog ──────────────────────────────────────────────────────────────
function openEditDialog() {
  if (!auth.user) return
  editForm.name = auth.user.name ?? ''
  editForm.phone = auth.user.phone ?? ''
  editForm.work_phone = auth.user.work_phone ?? ''
  editForm.job_title = auth.user.job_title ?? ''
  editForm.location = auth.user.location ?? ''
  editForm.address = auth.user.address ?? ''
  editForm.timezone = auth.user.timezone ?? 'America/Lima'
  editForm.language = auth.user.language ?? 'es'
  editForm.signature = auth.user.signature ?? ''
  editForm.time_format = auth.user.time_format ?? '24h'
  editDialogOpen.value = true
}

async function saveProfile() {
  savingProfile.value = true
  try {
    const res = await updateProfile({ ...editForm })
    auth.user = res.data
    editDialogOpen.value = false
    $q.notify({ type: 'positive', message: t('profile.profileUpdated') })
  } catch {
    // errors handled by api interceptor
  } finally {
    savingProfile.value = false
  }
}

// ─── Password dialog ──────────────────────────────────────────────────────────
function openPasswordDialog() {
  passwordForm.current_password = ''
  passwordForm.new_password = ''
  passwordForm.new_password_confirmation = ''
  showCurrentPassword.value = false
  showNewPassword.value = false
  showConfirmPassword.value = false
  passwordDialogOpen.value = true
}

async function savePassword() {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    $q.notify({ type: 'negative', message: 'Las contraseñas no coinciden' })
    return
  }
  savingPassword.value = true
  try {
    await changePassword({ ...passwordForm })
    passwordDialogOpen.value = false
    $q.notify({ type: 'positive', message: t('profile.passwordChanged') })
  } catch {
    // handled by interceptor
  } finally {
    savingPassword.value = false
  }
}

// ─── Avatar ───────────────────────────────────────────────────────────────────
const avatarFileInput = ref<HTMLInputElement | null>(null)

function triggerAvatarUpload() {
  avatarFileInput.value?.click()
}

async function onAvatarFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploadingAvatar.value = true
  try {
    const res = await uploadAvatar(file)
    auth.user = res.data
    $q.notify({ type: 'positive', message: 'Avatar actualizado' })
  } catch {
    // handled by interceptor
  } finally {
    uploadingAvatar.value = false
    input.value = ''
  }
}

async function onDeleteAvatar() {
  uploadingAvatar.value = true
  try {
    const res = await deleteAvatar()
    auth.user = res.data
    $q.notify({ type: 'positive', message: 'Avatar eliminado' })
  } catch {
    // handled by interceptor
  } finally {
    uploadingAvatar.value = false
  }
}

// ─── Availability toggle ──────────────────────────────────────────────────────
const isAvailable = ref(auth.user?.is_available_for_assignment ?? true)

async function toggleAvailability(val: boolean) {
  try {
    const res = await updateProfile({ is_available_for_assignment: val })
    auth.user = res.data
  } catch {
    isAvailable.value = !val
  }
}
</script>

<template>
  <q-page class="profile-page q-pa-md">
    <div v-if="loading" class="row justify-center q-py-xl">
      <q-spinner color="primary" size="40px" />
    </div>

    <div v-else class="row q-col-gutter-md">
      <!-- ── Main column ─────────────────────────────────────────────────── -->
      <div class="col-12 col-md-9">

        <!-- Basic Details card -->
        <q-card flat bordered class="q-mb-md profile-card">
          <q-card-section>
            <div class="row items-start q-col-gutter-md">

              <!-- Avatar -->
              <div class="col-auto">
                <div class="avatar-wrapper">
                  <q-avatar
                    size="120px"
                    color="primary"
                    text-color="white"
                    font-size="48px"
                    class="profile-avatar"
                  >
                    <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Avatar" />
                    <span v-else>{{ userInitial }}</span>
                  </q-avatar>

                  <!-- Hover overlay -->
                  <div class="avatar-overlay" @click="triggerAvatarUpload">
                    <q-spinner v-if="uploadingAvatar" color="white" size="24px" />
                    <q-icon v-else name="photo_camera" color="white" size="28px" />
                    <span class="avatar-overlay-text">{{ t('profile.uploadAvatar') }}</span>
                  </div>

                  <!-- Delete avatar button -->
                  <q-btn
                    v-if="auth.user?.avatar_url"
                    round
                    dense
                    flat
                    icon="close"
                    size="xs"
                    color="negative"
                    class="avatar-delete-btn"
                    @click.stop="onDeleteAvatar"
                  >
                    <q-tooltip>{{ t('profile.deleteAvatar') }}</q-tooltip>
                  </q-btn>

                  <input
                    ref="avatarFileInput"
                    type="file"
                    accept="image/*"
                    class="hidden-input"
                    @change="onAvatarFileChange"
                  />
                </div>
              </div>

              <!-- Name + fields -->
              <div class="col">
                <div class="text-h5 text-weight-bold q-mb-xs">{{ auth.user?.name }}</div>
                <div v-if="auth.user?.job_title" class="text-subtitle1 text-grey-6 q-mb-md">
                  {{ auth.user.job_title }}
                </div>
                <div v-else class="q-mb-md" />

                <div class="row q-col-gutter-md">
                  <!-- Email -->
                  <div class="col-12 col-sm-6">
                    <div class="field-label text-caption text-grey-6 q-mb-xs">
                      {{ t('profile.email') }}
                    </div>
                    <div class="field-value">{{ auth.user?.email }}</div>
                  </div>

                  <!-- Job title -->
                  <div class="col-12 col-sm-6">
                    <div class="field-label text-caption text-grey-6 q-mb-xs">
                      {{ t('profile.jobTitle') }}
                    </div>
                    <div class="field-value">{{ auth.user?.job_title || '—' }}</div>
                  </div>

                  <!-- Work phone -->
                  <div class="col-12 col-sm-6">
                    <div class="field-label text-caption text-grey-6 q-mb-xs">
                      {{ t('profile.workPhone') }}
                    </div>
                    <div class="field-value">{{ auth.user?.work_phone || '—' }}</div>
                  </div>

                  <!-- Mobile phone -->
                  <div class="col-12 col-sm-6">
                    <div class="field-label text-caption text-grey-6 q-mb-xs">
                      {{ t('profile.mobilePhone') }}
                    </div>
                    <div class="field-value">{{ auth.user?.phone || '—' }}</div>
                  </div>

                  <!-- Timezone -->
                  <div class="col-12 col-sm-6">
                    <div class="field-label text-caption text-grey-6 q-mb-xs">
                      {{ t('profile.timezone') }}
                    </div>
                    <div class="field-value">{{ timezoneLabel }}</div>
                  </div>

                  <!-- Language -->
                  <div class="col-12 col-sm-6">
                    <div class="field-label text-caption text-grey-6 q-mb-xs">
                      {{ t('profile.language') }}
                    </div>
                    <div class="field-value">{{ languageLabel }}</div>
                  </div>
                </div>
              </div>
            </div>
          </q-card-section>

          <q-card-actions class="q-px-md q-pb-md">
            <q-btn
              outline
              color="primary"
              :label="t('profile.editProfile')"
              icon="edit"
              @click="openEditDialog"
            />
          </q-card-actions>
        </q-card>

        <!-- Tabs -->
        <q-card flat bordered class="profile-card">
          <q-tabs
            v-model="activeTab"
            dense
            align="left"
            class="text-grey-7"
            active-color="primary"
            indicator-color="primary"
          >
            <q-tab name="profile" :label="t('profile.title')" />
          </q-tabs>

          <q-separator />

          <q-tab-panels v-model="activeTab" animated>
            <q-tab-panel name="profile" class="q-pa-none">
              <q-expansion-item
                :label="t('profile.additionalInfo')"
                icon="info_outline"
                default-opened
                class="q-pa-none"
              >
                <q-card-section>
                  <div class="row q-col-gutter-md">
                    <!-- Time format -->
                    <div class="col-12 col-sm-6">
                      <div class="field-label text-caption text-grey-6 q-mb-xs">
                        {{ t('profile.timeFormat') }}
                      </div>
                      <div class="field-value">
                        {{ auth.user?.time_format === '12h' ? '12 horas (1:00 PM)' : '24 horas (13:00)' }}
                      </div>
                    </div>

                    <!-- Department -->
                    <div class="col-12 col-sm-6">
                      <div class="field-label text-caption text-grey-6 q-mb-xs">
                        {{ t('profile.department') }}
                      </div>
                      <div class="field-value">{{ auth.user?.department?.name || '—' }}</div>
                    </div>

                    <!-- Address -->
                    <div class="col-12 col-sm-6">
                      <div class="field-label text-caption text-grey-6 q-mb-xs">
                        {{ t('profile.address') }}
                      </div>
                      <div class="field-value">{{ auth.user?.address || '—' }}</div>
                    </div>

                    <!-- Location -->
                    <div class="col-12 col-sm-6">
                      <div class="field-label text-caption text-grey-6 q-mb-xs">
                        {{ t('profile.location') }}
                      </div>
                      <div class="field-value">{{ auth.user?.location || '—' }}</div>
                    </div>

                    <!-- Signature -->
                    <div class="col-12">
                      <div class="field-label text-caption text-grey-6 q-mb-xs">
                        {{ t('profile.signature') }}
                      </div>
                      <div
                        v-if="auth.user?.signature"
                        class="field-value signature-preview"
                      >
                        {{ auth.user.signature }}
                      </div>
                      <div v-else class="field-value">—</div>
                    </div>
                  </div>
                </q-card-section>
              </q-expansion-item>
            </q-tab-panel>
          </q-tab-panels>
        </q-card>
      </div>

      <!-- ── Right sidebar ──────────────────────────────────────────────── -->
      <div class="col-12 col-md-3">

        <!-- Security card -->
        <q-card flat bordered class="q-mb-md profile-card">
          <q-card-section>
            <div class="text-subtitle2 text-weight-bold q-mb-md">
              <q-icon name="lock" class="q-mr-xs" />
              {{ t('profile.security') }}
            </div>
            <q-btn
              outline
              color="primary"
              :label="t('profile.changePassword')"
              icon="key"
              class="full-width"
              @click="openPasswordDialog"
            />
          </q-card-section>
        </q-card>

        <!-- Availability card (admin/agent only) -->
        <q-card
          v-if="auth.isAdmin || auth.isAgent"
          flat
          bordered
          class="profile-card"
        >
          <q-card-section>
            <div class="text-subtitle2 text-weight-bold q-mb-sm">
              <q-icon name="person_check" class="q-mr-xs" />
              {{ t('profile.availability') }}
            </div>
            <div class="row items-center justify-between">
              <div>
                <div class="text-body2">{{ t('profile.availableForAssignment') }}</div>
                <div class="text-caption text-grey-6">{{ t('profile.manuallyControlled') }}</div>
              </div>
              <q-toggle
                v-model="isAvailable"
                color="primary"
                @update:model-value="toggleAvailability"
              />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- ── Edit Profile Dialog ─────────────────────────────────────────────── -->
    <q-dialog v-model="editDialogOpen" persistent>
      <q-card style="width: 700px; max-width: 90vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ t('profile.editProfile') }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="row q-col-gutter-md">
            <div class="col-12 col-sm-6">
              <q-input
                v-model="editForm.name"
                :label="'Nombre completo'"
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-input
                v-model="editForm.job_title"
                :label="t('profile.jobTitle')"
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-input
                v-model="editForm.phone"
                :label="t('profile.mobilePhone')"
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-input
                v-model="editForm.work_phone"
                :label="t('profile.workPhone')"
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-select
                v-model="editForm.timezone"
                :options="timezoneOptions"
                :label="t('profile.timezone')"
                emit-value
                map-options
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-select
                v-model="editForm.language"
                :options="languageOptions"
                :label="t('profile.language')"
                emit-value
                map-options
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-select
                v-model="editForm.time_format"
                :options="timeFormatOptions"
                :label="t('profile.timeFormat')"
                emit-value
                map-options
                outlined
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-input
                v-model="editForm.location"
                :label="t('profile.location')"
                outlined
                dense
              />
            </div>
            <div class="col-12">
              <q-input
                v-model="editForm.address"
                :label="t('profile.address')"
                outlined
                dense
              />
            </div>
            <div class="col-12">
              <q-input
                v-model="editForm.signature"
                :label="t('profile.signature')"
                outlined
                dense
                type="textarea"
                rows="3"
                autogrow
              />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn
            unelevated
            color="primary"
            :label="t('common.save')"
            :loading="savingProfile"
            @click="saveProfile"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- ── Change Password Dialog ──────────────────────────────────────────── -->
    <q-dialog v-model="passwordDialogOpen" persistent>
      <q-card style="width: 480px; max-width: 90vw;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">{{ t('profile.changePassword') }}</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="column q-gutter-sm">
            <q-input
              v-model="passwordForm.current_password"
              :label="t('profile.currentPassword')"
              :type="showCurrentPassword ? 'text' : 'password'"
              outlined
              dense
            >
              <template #append>
                <q-icon
                  :name="showCurrentPassword ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  @click="showCurrentPassword = !showCurrentPassword"
                />
              </template>
            </q-input>

            <q-input
              v-model="passwordForm.new_password"
              :label="t('profile.newPassword')"
              :type="showNewPassword ? 'text' : 'password'"
              outlined
              dense
            >
              <template #append>
                <q-icon
                  :name="showNewPassword ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  @click="showNewPassword = !showNewPassword"
                />
              </template>
            </q-input>

            <q-input
              v-model="passwordForm.new_password_confirmation"
              :label="t('profile.confirmNewPassword')"
              :type="showConfirmPassword ? 'text' : 'password'"
              outlined
              dense
              :error="
                passwordForm.new_password_confirmation.length > 0 &&
                passwordForm.new_password !== passwordForm.new_password_confirmation
              "
              error-message="Las contraseñas no coinciden"
            >
              <template #append>
                <q-icon
                  :name="showConfirmPassword ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  @click="showConfirmPassword = !showConfirmPassword"
                />
              </template>
            </q-input>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat :label="t('common.cancel')" v-close-popup />
          <q-btn
            unelevated
            color="primary"
            :label="t('profile.changePassword')"
            :loading="savingPassword"
            :disable="
              !passwordForm.current_password ||
              !passwordForm.new_password ||
              passwordForm.new_password !== passwordForm.new_password_confirmation
            "
            @click="savePassword"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<style scoped>
.profile-page {
  max-width: 1200px;
  margin: 0 auto;
}

.profile-card {
  border-radius: 8px;
}

/* Avatar */
.avatar-wrapper {
  position: relative;
  width: 120px;
  height: 120px;
  cursor: pointer;
}

.profile-avatar {
  width: 120px;
  height: 120px;
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
  gap: 4px;
}

.avatar-wrapper:hover .avatar-overlay {
  opacity: 1;
}

.avatar-overlay-text {
  color: white;
  font-size: 10px;
  text-align: center;
  line-height: 1.2;
  padding: 0 4px;
}

.avatar-delete-btn {
  position: absolute;
  top: -4px;
  right: -4px;
  background: white;
  border: 1px solid #e0e0e0;
}

.hidden-input {
  display: none;
}

/* Fields */
.field-label {
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 11px;
}

.field-value {
  font-size: 14px;
}

/* Signature */
.signature-preview {
  white-space: pre-wrap;
  border-left: 3px solid #e0e0e0;
  padding-left: 12px;
  color: #555;
  font-style: italic;
}

/* Dark mode overrides */
.body--dark .avatar-delete-btn {
  background: #2d2d2d;
  border-color: #444;
}

.body--dark .signature-preview {
  border-left-color: #555;
  color: #aaa;
}
</style>
