import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { useCartStore, MAX_QUANTITY } from './cart'
import { makeProduct } from '@/test/helpers'

vi.mock('@/api/store', () => ({
  storeApi: { validateCart: vi.fn() },
}))
import { storeApi } from '@/api/store'

describe('cart store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('añade productos y acumula cantidades', () => {
    const cart = useCartStore()
    const p = makeProduct({ id: 1, price_cents: 1000, stock: 5 })
    expect(cart.add(p)).toBe('added')
    expect(cart.add(p, 2)).toBe('added')
    expect(cart.count).toBe(3)
    expect(cart.totalCents).toBe(3000)
  })

  it('nunca supera el stock ni el máximo por línea', () => {
    const cart = useCartStore()
    const scarce = makeProduct({ id: 2, stock: 3 })
    expect(cart.add(scarce, 5)).toBe('capped')
    expect(cart.lines[0].quantity).toBe(3)

    const plenty = makeProduct({ id: 3, stock: 100 })
    cart.add(plenty, 50)
    expect(cart.lines.find((l) => l.product_id === 3)?.quantity).toBe(MAX_QUANTITY)
  })

  it('no añade productos agotados', () => {
    const cart = useCartStore()
    expect(cart.add(makeProduct({ stock: 0 }))).toBe('out_of_stock')
    expect(cart.lines).toHaveLength(0)
  })

  it('setQuantity ignora negativos/ceros eliminando la línea y recorta al stock', () => {
    const cart = useCartStore()
    cart.add(makeProduct({ id: 4, stock: 4 }))
    cart.setQuantity(4, 99)
    expect(cart.lines[0].quantity).toBe(4)
    cart.setQuantity(4, -2)
    expect(cart.lines).toHaveLength(0)
  })

  it('persiste en localStorage y descarta datos corruptos', async () => {
    const cart = useCartStore()
    cart.add(makeProduct({ id: 5 }))
    await nextTick()
    expect(JSON.parse(localStorage.getItem('kcf.cart.v1')!)).toHaveLength(1)

    localStorage.setItem('kcf.cart.v1', JSON.stringify([{ product_id: 'x', quantity: -1 }]))
    setActivePinia(createPinia())
    expect(useCartStore().lines).toHaveLength(0)
  })

  it('sincroniza precio y stock con el servidor y registra problemas', async () => {
    const cart = useCartStore()
    cart.add(makeProduct({ id: 6, price_cents: 1000, stock: 10 }), 4)
    vi.mocked(storeApi.validateCart).mockResolvedValue({
      valid: false,
      lines: [{ product_id: 6, sku: 'X', name: 'X', unit_price_cents: 1200, quantity: 4, line_total_cents: 4800, available_stock: 2 }],
      issues: [{ product_id: 6, code: 'insufficient_stock', available: 2, requested: 4 }],
      totals: { items_count: 4, subtotal_cents: 0, tax_cents: 0, total_cents: 4800 },
    })

    expect(await cart.validate()).toBe(false)
    expect(cart.lines[0].price_cents).toBe(1200)
    expect(cart.lines[0].stock).toBe(2)
    expect(cart.hasIssues).toBe(true)
    expect(storeApi.validateCart).toHaveBeenCalledWith([{ product_id: 6, quantity: 4 }])

    cart.setQuantity(6, 2)
    expect(cart.issueFor(6)).toBeUndefined()
    expect(cart.hasIssues).toBe(false)
  })
})
