/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

import 'vue-router'

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    roles?: string[]
  }
}
