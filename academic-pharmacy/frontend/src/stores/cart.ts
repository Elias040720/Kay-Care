import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { storeApi } from '@/api/store'
import type { CartIssue, Illustration, Product } from '@/types'
import { readJson, writeJson } from '@/utils/storage'

/** Tope por línea, igual que el backend (CartValidateRequest::MAX_QUANTITY). */
export const MAX_QUANTITY = 10
export const MAX_LINES = 20
const STORAGE_KEY = 'kcf.cart.v1'

export interface CartLine {
  product_id: number
  slug: string
  name: string
  brand: string
  price_cents: number
  stock: number
  quantity: number
  illustration: Illustration
  image_url: string | null
}

export type AddResult = 'added' | 'capped' | 'out_of_stock' | 'cart_full'

export const useCartStore = defineStore('cart', () => {
  const lines = ref<CartLine[]>(readJson<CartLine[]>('local', STORAGE_KEY, []).filter(isValidLine))
  const isOpen = ref(false)
  const issues = ref<CartIssue[]>([])
  const validating = ref(false)

  watch(lines, (value) => writeJson('local', STORAGE_KEY, value), { deep: true })

  const count = computed(() => lines.value.reduce((sum, l) => sum + l.quantity, 0))
  // Estimación en el cliente solo para mostrar; el total real lo calcula el servidor.
  const totalCents = computed(() => lines.value.reduce((sum, l) => sum + l.price_cents * l.quantity, 0))
  const hasIssues = computed(() => issues.value.length > 0 || lines.value.some((l) => l.quantity > l.stock))

  function maxFor(line: Pick<CartLine, 'stock'>): number {
    return Math.max(0, Math.min(MAX_QUANTITY, line.stock))
  }

  function add(product: Product, quantity = 1): AddResult {
    if (product.stock <= 0) return 'out_of_stock'
    const existing = lines.value.find((l) => l.product_id === product.id)
    if (!existing && lines.value.length >= MAX_LINES) return 'cart_full'

    const limit = Math.min(MAX_QUANTITY, product.stock)
    const current = existing?.quantity ?? 0
    const next = Math.min(limit, current + Math.max(1, Math.floor(quantity)))

    if (existing) {
      Object.assign(existing, { quantity: next, stock: product.stock, price_cents: product.price_cents })
    } else {
      lines.value.push({
        product_id: product.id,
        slug: product.slug,
        name: product.name,
        brand: product.brand,
        price_cents: product.price_cents,
        stock: product.stock,
        quantity: next,
        illustration: product.illustration,
        image_url: product.image_url,
      })
    }
    clearIssue(product.id)
    return next - current < quantity ? 'capped' : 'added'
  }

  function setQuantity(productId: number, quantity: number) {
    const line = lines.value.find((l) => l.product_id === productId)
    if (!line) return
    const q = Math.floor(Number(quantity))
    if (!Number.isFinite(q) || q < 1) {
      remove(productId)
      return
    }
    line.quantity = Math.min(q, maxFor(line))
    clearIssue(productId)
  }

  function remove(productId: number) {
    lines.value = lines.value.filter((l) => l.product_id !== productId)
    clearIssue(productId)
  }

  function clear() {
    lines.value = []
    issues.value = []
  }

  function clearIssue(productId: number) {
    issues.value = issues.value.filter((i) => i.product_id !== productId)
  }

  function payload() {
    return lines.value.map((l) => ({ product_id: l.product_id, quantity: l.quantity }))
  }

  /** Sincroniza precio y stock con el servidor y marca los problemas. */
  async function validate(): Promise<boolean> {
    if (lines.value.length === 0) {
      issues.value = []
      return true
    }
    validating.value = true
    try {
      const result = await storeApi.validateCart(payload())
      for (const serverLine of result.lines) {
        const line = lines.value.find((l) => l.product_id === serverLine.product_id)
        if (line) {
          line.price_cents = serverLine.unit_price_cents
          line.stock = serverLine.available_stock
        }
      }
      for (const issue of result.issues) {
        if (issue.code === 'unavailable') {
          const line = lines.value.find((l) => l.product_id === issue.product_id)
          if (line) line.stock = 0
        }
      }
      issues.value = result.issues
      return result.valid
    } finally {
      validating.value = false
    }
  }

  function applyIssues(serverIssues: CartIssue[]) {
    issues.value = serverIssues
    for (const issue of serverIssues) {
      const line = lines.value.find((l) => l.product_id === issue.product_id)
      if (line) line.stock = issue.available
    }
  }

  function issueFor(productId: number): CartIssue | undefined {
    return issues.value.find((i) => i.product_id === productId)
  }

  return {
    lines, isOpen, issues, validating, count, totalCents, hasIssues,
    add, setQuantity, remove, clear, validate, payload, applyIssues, issueFor, maxFor,
    open: () => { isOpen.value = true },
    close: () => { isOpen.value = false },
  }
})

function isValidLine(line: unknown): line is CartLine {
  const l = line as CartLine
  return !!l && Number.isInteger(l.product_id) && Number.isInteger(l.quantity) && l.quantity > 0 && Number.isInteger(l.price_cents)
}
