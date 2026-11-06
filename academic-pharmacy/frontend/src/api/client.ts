/**
 * Cliente HTTP mínimo sobre fetch.
 *
 * - Mismo origen: nginx (prod) o Vite (dev) hacen proxy de /api.
 * - Cookies de sesión HttpOnly + token CSRF (patrón Sanctum SPA): antes de
 *   cualquier petición que modifica datos se obtiene la cookie XSRF-TOKEN y
 *   se reenvía en la cabecera X-XSRF-TOKEN.
 * - Errores normalizados en ApiError con el `code` que devuelve la API.
 */
export const API_BASE = '/api/v1/store'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string,
    public readonly errors: Record<string, string[]> = {},
    public readonly details: Record<string, unknown> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }

  firstFieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface RequestOptions {
  query?: Record<string, string | number | boolean | null | undefined>
  body?: unknown
  headers?: Record<string, string>
  signal?: AbortSignal
}

function readCookie(name: string): string | null {
  const match = document.cookie.split('; ').find((c) => c.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

let csrfPromise: Promise<void> | null = null

export async function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && readCookie('XSRF-TOKEN')) return
  csrfPromise ??= fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })
    .then(() => undefined)
    .finally(() => {
      csrfPromise = null
    })
  return csrfPromise
}

export function buildUrl(path: string, query?: RequestOptions['query']): string {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value === undefined || value === null || value === '' || value === false) continue
    params.set(key, value === true ? '1' : String(value))
  }
  const qs = params.toString()
  return `${API_BASE}${path}${qs ? `?${qs}` : ''}`
}

async function parse(response: Response): Promise<unknown> {
  const text = await response.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

export async function request<T>(method: Method, path: string, options: RequestOptions = {}, retried = false): Promise<T> {
  const mutating = method !== 'GET'
  if (mutating) await ensureCsrfCookie()

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...options.headers,
  }
  let body: BodyInit | undefined
  if (options.body instanceof FormData) {
    body = options.body
  } else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }
  const xsrf = readCookie('XSRF-TOKEN')
  if (mutating && xsrf) headers['X-XSRF-TOKEN'] = xsrf

  let response: Response
  try {
    response = await fetch(buildUrl(path, options.query), {
      method,
      headers,
      body,
      credentials: 'same-origin',
      signal: options.signal,
    })
  } catch (error) {
    if ((error as Error).name === 'AbortError') throw error
    throw new ApiError('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.', 0, 'network_error')
  }

  // Token CSRF caducado: se renueva una sola vez y se reintenta.
  if (response.status === 419 && mutating && !retried) {
    await ensureCsrfCookie(true)
    return request<T>(method, path, options, true)
  }

  const payload = (await parse(response)) as Record<string, unknown> | null

  if (!response.ok) {
    throw new ApiError(
      (payload?.message as string) ?? 'Ocurrió un error inesperado.',
      response.status,
      (payload?.code as string) ?? 'http_error',
      (payload?.errors as Record<string, string[]>) ?? {},
      (payload?.details as Record<string, unknown>) ?? {},
    )
  }

  return payload as T
}

export const http = {
  get: <T>(path: string, options?: RequestOptions) => request<T>('GET', path, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) => request<T>('POST', path, { ...options, body }),
  put: <T>(path: string, body?: unknown, options?: RequestOptions) => request<T>('PUT', path, { ...options, body }),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) => request<T>('PATCH', path, { ...options, body }),
  delete: <T>(path: string, options?: RequestOptions) => request<T>('DELETE', path, options),
}
