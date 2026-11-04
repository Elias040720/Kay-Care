import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import CheckoutView from './CheckoutView.vue'
import ConfirmationView from './ConfirmationView.vue'
import { axeViolations, makeProduct, mountWithApp } from '@/test/helpers'
import { useCartStore } from '@/stores/cart'
import { ApiError } from '@/api/client'
import type { Order } from '@/types'

vi.mock('@/api/store', () => ({
  storeApi: { validateCart: vi.fn(), checkout: vi.fn(), order: vi.fn() },
}))
import { storeApi } from '@/api/store'

const NOTICE = 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES'

const order: Order = {
  id: 1, number: 'KCF-2026-000042', buyer_alias: 'Comprador Demo 07', status: 'pending', status_label: 'Recibido',
  allowed_transitions: ['confirmed', 'cancelled'], items_count: 2, subtotal_cents: 3373, tax_cents: 607, total_cents: 3980,
  created_at: '2026-11-04T10:00:00Z', status_changed_at: null, simulated: true,
  items: [{ product_id: 1, product_name: 'Vitamina C', sku: 'KCF-VIT-001', unit_price_cents: 1990, quantity: 2, line_total_cents: 3980 }],
}

async function mountCheckoutWithItems() {
  const ctx = await mountWithApp(CheckoutView, '/checkout')
  useCartStore().add(makeProduct({ id: 1, name: 'Vitamina C', price_cents: 1990 }), 2)
  await flushPromises()
  return ctx
}

describe('CheckoutView', () => {
  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
    vi.mocked(storeApi.validateCart).mockResolvedValue({ valid: true, lines: [], issues: [], totals: { items_count: 0, subtotal_cents: 0, tax_cents: 0, total_cents: 0 } })
  })
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('muestra el aviso académico y pide solo un alias ficticio', async () => {
    const { wrapper } = await mountCheckoutWithItems()
    expect(wrapper.text()).toContain(NOTICE)
    const inputs = wrapper.findAll('form input:not([type="checkbox"])')
    expect(inputs).toHaveLength(1)
    const labels = wrapper.findAll('form label').map((l) => l.text()).join(' ')
    expect(labels).not.toMatch(/DNI|tarjeta|direcci[oó]n|tel[eé]fono|correo/i)
    wrapper.unmount()
  })

  it('valida el alias y el consentimiento antes de enviar', async () => {
    const { wrapper } = await mountCheckoutWithItems()
    await wrapper.get('[data-testid="buyer-alias"]').setValue('45879632')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('No ingreses documentos')
    expect(wrapper.text()).toContain('Confirma que entiendes')
    expect(storeApi.checkout).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('envía el pedido con clave de idempotencia y navega a la confirmación', async () => {
    vi.mocked(storeApi.checkout).mockResolvedValue({ data: order, lookup_token: 'tok123', replayed: false, notice: NOTICE })
    const { wrapper, router } = await mountCheckoutWithItems()

    await wrapper.get('[data-testid="buyer-alias"]').setValue('Comprador Demo 07')
    await wrapper.get('[data-testid="accept-terms"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(storeApi.checkout).toHaveBeenCalledWith(
      { buyer_alias: 'Comprador Demo 07', accept_demo_terms: true, items: [{ product_id: 1, quantity: 2 }] },
      expect.stringMatching(/^kcf-[A-Za-z0-9-]{16,}$/),
    )
    expect(router.currentRoute.value.name).toBe('confirmation')
    expect(router.currentRoute.value.query.token).toBe('tok123')
    expect(useCartStore().lines).toHaveLength(0)
    wrapper.unmount()
  })

  it('reutiliza la misma clave al reintentar tras un fallo de red', async () => {
    vi.mocked(storeApi.checkout).mockRejectedValueOnce(new ApiError('Sin conexión', 0, 'network_error'))
    const { wrapper } = await mountCheckoutWithItems()
    await wrapper.get('[data-testid="buyer-alias"]').setValue('Comprador Demo 07')
    await wrapper.get('[data-testid="accept-terms"]').setValue(true)

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[data-testid="checkout-error"]').text()).toContain('Sin conexión')

    vi.mocked(storeApi.checkout).mockResolvedValueOnce({ data: order, lookup_token: 't', replayed: true, notice: NOTICE })
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const [firstKey, secondKey] = vi.mocked(storeApi.checkout).mock.calls.map((c) => c[1])
    expect(firstKey).toBe(secondKey)
    wrapper.unmount()
  })

  it('marca las líneas sin stock cuando el servidor responde 409', async () => {
    vi.mocked(storeApi.checkout).mockRejectedValue(
      new ApiError('Sin stock', 409, 'insufficient_stock', {}, { issues: [{ product_id: 1, code: 'insufficient_stock', available: 1, requested: 2 }] }),
    )
    const { wrapper } = await mountCheckoutWithItems()
    await wrapper.get('[data-testid="buyer-alias"]').setValue('Comprador Demo 07')
    await wrapper.get('[data-testid="accept-terms"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('El stock cambió')
    expect(wrapper.text()).toContain('Stock disponible: 1')
    expect(wrapper.get('[data-testid="place-order"]').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('no tiene violaciones básicas de accesibilidad', async () => {
    const { wrapper } = await mountCheckoutWithItems()
    expect(await axeViolations(wrapper.element)).toEqual([])
    wrapper.unmount()
  })
})

describe('ConfirmationView', () => {
  it('muestra número de pedido, total y aviso académico', async () => {
    vi.mocked(storeApi.order).mockResolvedValue(order)
    const { wrapper } = await mountWithApp(ConfirmationView, '/pedido/KCF-2026-000042?token=tok123', { props: { number: 'KCF-2026-000042' } })

    expect(storeApi.order).toHaveBeenCalledWith('KCF-2026-000042', 'tok123')
    expect(wrapper.get('[data-testid="order-number"]').text()).toBe('KCF-2026-000042')
    expect(wrapper.get('[data-testid="order-total"]').text()).toMatch(/39\.80/)
    expect(wrapper.text()).toContain(NOTICE)
    expect(await axeViolations(wrapper.element)).toEqual([])
    wrapper.unmount()
  })

  it('sin token no consulta la API y muestra un estado vacío', async () => {
    vi.mocked(storeApi.order).mockClear()
    const { wrapper } = await mountWithApp(ConfirmationView, '/pedido/KCF-2026-000042', { props: { number: 'KCF-2026-000042' } })
    expect(storeApi.order).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('No encontramos ese pedido')
    wrapper.unmount()
  })
})
