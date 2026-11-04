import type { OrderStatus, StockStatus } from '@/types'

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', minimumFractionDigits: 2 })

/** Céntimos → "S/ 24.90". Los importes viajan siempre como enteros. */
export function formatMoney(cents: number): string {
  return currency.format((Number.isFinite(cents) ? cents : 0) / 100)
}

export function formatDate(iso: string | null | undefined, withTime = true): string {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('es-PE', withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(date)
}

export const STOCK_LABELS: Record<StockStatus, string> = {
  in_stock: 'Disponible',
  low_stock: 'Últimas unidades',
  out_of_stock: 'Agotado',
}

export const ORDER_STATUS_LABELS: Record<OrderStatus, string> = {
  pending: 'Recibido',
  confirmed: 'Confirmado',
  preparing: 'En preparación',
  ready: 'Listo para recoger',
  delivered: 'Entregado',
  cancelled: 'Cancelado',
}

export const ORDER_STATUS_TONES: Record<OrderStatus, string> = {
  pending: 'bg-honey-100 text-honey-600',
  confirmed: 'bg-teal-100 text-teal-700',
  preparing: 'bg-teal-50 text-teal-600',
  ready: 'bg-sage-100 text-sage-700',
  delivered: 'bg-sage-200 text-sage-800',
  cancelled: 'bg-clay-100 text-clay-700',
}

/** Clave de idempotencia aleatoria y criptográficamente segura. */
export function newIdempotencyKey(): string {
  const c = globalThis.crypto as Crypto
  if (typeof c.randomUUID === 'function') {
    return `kcf-${c.randomUUID()}`
  }
  // randomUUID solo existe en contextos seguros (HTTPS/localhost).
  const bytes = new Uint8Array(16)
  c.getRandomValues(bytes)
  return `kcf-${Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')}`
}

/** Mismas reglas que el backend: alias ficticio, sin documentos ni teléfonos. */
export function validateBuyerAlias(value: string): string | null {
  const alias = value.trim()
  if (alias.length < 3) return 'Usa al menos 3 caracteres.'
  if (alias.length > 40) return 'Máximo 40 caracteres.'
  if (!/^[\p{L}0-9 _-]+$/u.test(alias)) return 'Solo letras, números, espacios, guiones y guion bajo.'
  if (/\d{7,}/.test(alias)) return 'No ingreses documentos ni teléfonos: usa un alias ficticio.'
  return null
}

export const DEMO_NOTICE = 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES'
