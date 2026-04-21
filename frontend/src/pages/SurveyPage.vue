<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { getSurveyByToken, submitSurvey, type SurveyByToken } from '@/api/satisfactionSurveys'

const route = useRoute()
const token = route.params.token as string

const loading = ref(true)
const submitting = ref(false)
const survey = ref<SurveyByToken | null>(null)
const selectedRating = ref<number>(0)
const comment = ref('')
const submitted = ref(false)
const alreadyResponded = ref(false)
const error = ref<string | null>(null)
const hoverRating = ref(0)

const ratingLabels = ['', 'Muy insatisfecho', 'Insatisfecho', 'Neutral', 'Satisfecho', 'Muy satisfecho']
const ratingEmojis = ['', '\u{1F61E}', '\u{1F641}', '\u{1F610}', '\u{1F642}', '\u{1F929}']

const displayRating = computed(() => hoverRating.value || selectedRating.value)

onMounted(async () => {
  try {
    const res = await getSurveyByToken(token)
    survey.value = res.data

    if (res.data.responded_at) {
      alreadyResponded.value = true
      selectedRating.value = res.data.rating || 0
      comment.value = res.data.comment || ''
    } else {
      // Pre-select rating from URL query param
      const qRating = Number(route.query.rating)
      if (qRating >= 1 && qRating <= 5) {
        selectedRating.value = qRating
      }
    }
  } catch (e: any) {
    error.value = 'No se pudo cargar la encuesta. El enlace puede ser invalido o haber expirado.'
  } finally {
    loading.value = false
  }
})

async function onSubmit() {
  if (selectedRating.value < 1) return
  submitting.value = true
  try {
    await submitSurvey(token, {
      rating: selectedRating.value,
      comment: comment.value.trim() || undefined,
    })
    submitted.value = true
  } catch (e: any) {
    if (e.response?.status === 409) {
      alreadyResponded.value = true
    } else {
      error.value = 'Error al enviar la encuesta. Intente nuevamente.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="survey-page">
    <div class="survey-container">
      <!-- Header -->
      <div class="survey-header">
        <div class="survey-logo">Chuyma</div>
        <div class="survey-header-subtitle">Encuesta de satisfaccion</div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="survey-body survey-center">
        <div class="survey-spinner"></div>
        <p class="survey-text-muted">Cargando encuesta...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="survey-body survey-center">
        <div class="survey-icon-large">&#x26A0;&#xFE0F;</div>
        <h2 class="survey-title">Enlace no disponible</h2>
        <p class="survey-text-muted">{{ error }}</p>
      </div>

      <!-- Already responded -->
      <div v-else-if="alreadyResponded && !submitted" class="survey-body survey-center">
        <div class="survey-icon-large">&#x2705;</div>
        <h2 class="survey-title">Ya respondiste esta encuesta</h2>
        <p class="survey-text-muted">Gracias por tu tiempo. Tu calificacion fue registrada.</p>
        <div class="survey-previous-rating">
          <div class="star-display">
            <span
              v-for="i in 5"
              :key="i"
              class="star-icon"
              :class="{ filled: i <= selectedRating }"
            >&#9733;</span>
          </div>
          <div class="rating-label">{{ ratingLabels[selectedRating] }}</div>
        </div>
        <div v-if="comment" class="survey-previous-comment">
          <div class="comment-label">Tu comentario:</div>
          <div class="comment-text">{{ comment }}</div>
        </div>
      </div>

      <!-- Thank you (after submit) -->
      <div v-else-if="submitted" class="survey-body survey-center">
        <div class="survey-icon-large thank-you-icon">&#127881;</div>
        <h2 class="survey-title">Gracias por tu respuesta</h2>
        <p class="survey-text-muted">
          Tu opinion nos ayuda a mejorar continuamente nuestro servicio de soporte.
        </p>
        <div class="survey-submitted-rating">
          <div class="star-display large">
            <span
              v-for="i in 5"
              :key="i"
              class="star-icon"
              :class="{ filled: i <= selectedRating }"
            >&#9733;</span>
          </div>
          <div class="rating-label">{{ ratingLabels[selectedRating] }}</div>
        </div>
      </div>

      <!-- Survey form -->
      <div v-else class="survey-body">
        <!-- Ticket info -->
        <div v-if="survey?.ticket" class="ticket-info-card">
          <div class="ticket-info-label">Ticket</div>
          <div class="ticket-info-value">
            <strong>{{ survey.ticket.ticket_number }}</strong> &mdash; {{ survey.ticket.title }}
          </div>
          <div v-if="survey.ticket.agent_name" class="ticket-info-agent">
            Atendido por: <strong>{{ survey.ticket.agent_name }}</strong>
          </div>
        </div>

        <h2 class="survey-question">¿Como calificarias tu experiencia de soporte?</h2>

        <!-- Star rating -->
        <div class="star-rating-container">
          <button
            v-for="i in 5"
            :key="i"
            type="button"
            class="star-button"
            :class="{ active: i <= displayRating }"
            @click="selectedRating = i"
            @mouseenter="hoverRating = i"
            @mouseleave="hoverRating = 0"
          >
            <span class="star-emoji">{{ ratingEmojis[i] }}</span>
            <span class="star-label">{{ i }}</span>
          </button>
        </div>

        <div class="rating-indicator" :class="{ visible: displayRating > 0 }">
          {{ ratingLabels[displayRating] }}
        </div>

        <!-- Comment -->
        <div class="comment-section">
          <label class="comment-form-label">
            ¿Deseas dejarnos un comentario? <span class="optional-tag">(opcional)</span>
          </label>
          <textarea
            v-model="comment"
            class="comment-textarea"
            rows="4"
            maxlength="1000"
            placeholder="Cuentanos sobre tu experiencia..."
          ></textarea>
          <div class="char-count">{{ comment.length }} / 1000</div>
        </div>

        <!-- Submit -->
        <button
          class="submit-button"
          :disabled="selectedRating < 1 || submitting"
          @click="onSubmit"
        >
          <span v-if="submitting" class="button-spinner"></span>
          <span v-else>Enviar calificacion</span>
        </button>
      </div>

      <!-- Footer -->
      <div class="survey-footer">
        <p>Chuyma &mdash; Plataforma ITSM</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Reset for standalone page */
.survey-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  color: #333;
}

.survey-container {
  width: 100%;
  max-width: 560px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

/* Header */
.survey-header {
  background: #1976d2;
  padding: 24px 32px;
  text-align: center;
}

.survey-logo {
  color: #fff;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.survey-header-subtitle {
  color: rgba(255, 255, 255, 0.8);
  font-size: 13px;
  margin-top: 4px;
}

/* Body */
.survey-body {
  padding: 32px;
}

.survey-center {
  text-align: center;
  padding: 48px 32px;
}

.survey-icon-large {
  font-size: 48px;
  margin-bottom: 16px;
}

.thank-you-icon {
  animation: bounceIn 0.6s ease-out;
}

@keyframes bounceIn {
  0% { transform: scale(0.3); opacity: 0; }
  50% { transform: scale(1.05); }
  70% { transform: scale(0.9); }
  100% { transform: scale(1); opacity: 1; }
}

.survey-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0 0 8px;
}

.survey-text-muted {
  color: #666;
  font-size: 15px;
  line-height: 1.5;
  margin: 0;
}

/* Ticket info */
.ticket-info-card {
  background: #f8f9fa;
  border-radius: 10px;
  padding: 16px 20px;
  margin-bottom: 28px;
  border-left: 4px solid #1976d2;
}

.ticket-info-label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #888;
  margin-bottom: 4px;
  font-weight: 600;
}

.ticket-info-value {
  font-size: 15px;
  color: #333;
}

.ticket-info-agent {
  font-size: 13px;
  color: #666;
  margin-top: 6px;
}

/* Question */
.survey-question {
  font-size: 20px;
  font-weight: 700;
  color: #1a1a2e;
  text-align: center;
  margin: 0 0 24px;
}

/* Star rating */
.star-rating-container {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-bottom: 12px;
}

.star-button {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 14px;
  border: 2px solid #e0e0e0;
  border-radius: 14px;
  background: #fff;
  cursor: pointer;
  transition: all 0.2s ease;
  min-width: 68px;
}

.star-button:hover {
  border-color: #1976d2;
  background: #e3f2fd;
  transform: translateY(-2px);
}

.star-button.active {
  border-color: #1976d2;
  background: #e3f2fd;
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.2);
}

.star-emoji {
  font-size: 32px;
  line-height: 1;
}

.star-label {
  font-size: 12px;
  font-weight: 600;
  color: #888;
}

.star-button.active .star-label {
  color: #1976d2;
}

.rating-indicator {
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  color: #1976d2;
  height: 20px;
  margin-bottom: 24px;
  opacity: 0;
  transition: opacity 0.2s;
}

.rating-indicator.visible {
  opacity: 1;
}

/* Comment */
.comment-section {
  margin-bottom: 24px;
}

.comment-form-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
}

.optional-tag {
  font-weight: 400;
  color: #999;
}

.comment-textarea {
  width: 100%;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.comment-textarea:focus {
  outline: none;
  border-color: #1976d2;
}

.char-count {
  text-align: right;
  font-size: 12px;
  color: #999;
  margin-top: 4px;
}

/* Submit button */
.submit-button {
  width: 100%;
  padding: 14px;
  background: #1976d2;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.1s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.submit-button:hover:not(:disabled) {
  background: #1565c0;
}

.submit-button:active:not(:disabled) {
  transform: scale(0.98);
}

.submit-button:disabled {
  background: #bbb;
  cursor: not-allowed;
}

/* Spinner */
.survey-spinner,
.button-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid rgba(0, 0, 0, 0.1);
  border-top-color: #1976d2;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto;
}

.button-spinner {
  width: 20px;
  height: 20px;
  border-top-color: #fff;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Previous rating display */
.survey-previous-rating,
.survey-submitted-rating {
  margin-top: 24px;
}

.star-display {
  display: flex;
  justify-content: center;
  gap: 4px;
}

.star-display.large {
  gap: 6px;
}

.star-icon {
  font-size: 28px;
  color: #ddd;
}

.star-display.large .star-icon {
  font-size: 36px;
}

.star-icon.filled {
  color: #ffc107;
}

.rating-label {
  font-size: 14px;
  color: #666;
  margin-top: 8px;
  text-align: center;
}

.survey-previous-comment {
  margin-top: 20px;
  background: #f8f9fa;
  border-radius: 10px;
  padding: 16px;
  text-align: left;
}

.comment-label {
  font-size: 12px;
  font-weight: 600;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 6px;
}

.comment-text {
  font-size: 14px;
  color: #333;
  line-height: 1.5;
}

/* Footer */
.survey-footer {
  border-top: 1px solid #eee;
  padding: 16px 32px;
  text-align: center;
}

.survey-footer p {
  margin: 0;
  font-size: 12px;
  color: #999;
}

/* Responsive */
@media (max-width: 480px) {
  .survey-page {
    padding: 0;
    align-items: stretch;
  }

  .survey-container {
    border-radius: 0;
    min-height: 100vh;
  }

  .star-rating-container {
    gap: 6px;
  }

  .star-button {
    padding: 10px 8px;
    min-width: 56px;
  }

  .star-emoji {
    font-size: 26px;
  }
}
</style>
