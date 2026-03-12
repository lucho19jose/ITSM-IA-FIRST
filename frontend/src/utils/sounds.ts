/**
 * Notification sounds using Web Audio API.
 * Zero external files — generates tones programmatically.
 */

let audioCtx: AudioContext | null = null

function getContext(): AudioContext {
  if (!audioCtx) audioCtx = new AudioContext()
  return audioCtx
}

function playTone(frequency: number, duration: number, type: OscillatorType = 'sine', volume = 0.3) {
  const ctx = getContext()
  // Resume context if suspended (browser autoplay policy)
  if (ctx.state === 'suspended') ctx.resume()

  const osc = ctx.createOscillator()
  const gain = ctx.createGain()

  osc.type = type
  osc.frequency.setValueAtTime(frequency, ctx.currentTime)

  gain.gain.setValueAtTime(volume, ctx.currentTime)
  gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration)

  osc.connect(gain)
  gain.connect(ctx.destination)

  osc.start(ctx.currentTime)
  osc.stop(ctx.currentTime + duration)
}

/** New ticket created — friendly double chime */
export function playNewTicket() {
  if (!isSoundEnabled()) return
  playTone(880, 0.15, 'sine', 0.25)
  setTimeout(() => playTone(1100, 0.2, 'sine', 0.25), 150)
}

/** Ticket assigned to you — attention triple beep */
export function playAssigned() {
  if (!isSoundEnabled()) return
  playTone(660, 0.1, 'sine', 0.2)
  setTimeout(() => playTone(880, 0.1, 'sine', 0.2), 120)
  setTimeout(() => playTone(1100, 0.15, 'sine', 0.25), 240)
}

/** New comment/notification — subtle single ping */
export function playNotification() {
  if (!isSoundEnabled()) return
  playTone(1000, 0.12, 'sine', 0.2)
}

/** SLA warning — urgent two-tone alert */
export function playSlaWarning() {
  if (!isSoundEnabled()) return
  playTone(800, 0.2, 'square', 0.15)
  setTimeout(() => playTone(600, 0.3, 'square', 0.15), 250)
}

// ─── Preferences ─────────────────────────────────────────────────────────────

const STORAGE_KEY = 'autoservice_sound'

export function isSoundEnabled(): boolean {
  return localStorage.getItem(STORAGE_KEY) !== '0'
}

export function setSoundEnabled(enabled: boolean) {
  localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0')
}
