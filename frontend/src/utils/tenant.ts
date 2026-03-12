const APP_DOMAIN = 'autoservice.test'

export function getSubdomain(): string | null {
  const host = window.location.hostname

  if (host === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(host)) {
    return null
  }

  const subdomain = host.replace(`.${APP_DOMAIN}`, '')

  if (subdomain === host || subdomain === 'www' || subdomain === '') {
    return null
  }

  return subdomain
}

export function isSubdomainAccess(): boolean {
  return getSubdomain() !== null
}

export function isCustomDomainAccess(): boolean {
  const host = window.location.hostname
  if (host === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(host)) {
    return false
  }
  // If host doesn't end with our app domain, it's a custom domain
  return !host.endsWith(APP_DOMAIN)
}

export function isTenantAccess(): boolean {
  return isSubdomainAccess() || isCustomDomainAccess()
}

export function getTenantUrl(slug: string): string {
  const protocol = window.location.protocol
  return `${protocol}//${slug}.${APP_DOMAIN}`
}
