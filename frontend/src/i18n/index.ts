import { createI18n } from 'vue-i18n'
import es from './es'
import en from './en'

export default createI18n({
  locale: 'es',
  fallbackLocale: 'en',
  legacy: false,
  messages: { es, en },
})
