import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import App from './App.vue'
import HomeView from './views/HomeView.vue'
import { axeViolations, categories, makeProduct, mountWithApp, page } from '@/test/helpers'
import { useCartStore } from '@/stores/cart'

vi.mock('@/api/store', () => ({
  storeApi: {
    products: vi.fn(async () => page([makeProduct({ id: 1, name: 'Protector solar', is_featured: true })])),
    categories: vi.fn(async () => categories),
    validateCart: vi.fn(async () => ({ valid: true, lines: [], issues: [], totals: { items_count: 0, subtotal_cents: 0, tax_cents: 0, total_cents: 0 } })),
  },
}))
vi.mock('@/api/admin', () => ({
  adminApi: { me: vi.fn(async () => { const { ApiError } = await import('@/api/client'); throw new ApiError('No', 401, 'unauthenticated') }) },
}))

describe('App shell', () => {
  afterEach(() => {
    document.body.innerHTML = ''
    localStorage.clear()
  })

  it('renderiza cabecera con aviso, navegación y pie', async () => {
    const { wrapper } = await mountWithApp(App, '/')
    expect(wrapper.find('header').exists()).toBe(true)
    expect(wrapper.text()).toContain('PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES')
    expect(wrapper.find('nav[aria-label="Principal"]').exists()).toBe(true)
    expect(wrapper.find('footer').exists()).toBe(true)
    expect(wrapper.find('a.skip-link').attributes('href')).toBe('#contenido')
    wrapper.unmount()
  })

  it('abre el drawer del carrito con sus cantidades', async () => {
    const { wrapper } = await mountWithApp(App, '/')
    useCartStore().add(makeProduct({ id: 9, name: 'Vitamina C', price_cents: 2490 }), 2)
    await wrapper.get('[data-testid="open-cart"]').trigger('click')
    await flushPromises()

    const drawer = document.querySelector('[data-testid="cart-drawer"]')!
    expect(drawer).not.toBeNull()
    expect(drawer.getAttribute('role')).toBe('dialog')
    expect(drawer.querySelectorAll('[data-testid="cart-line"]')).toHaveLength(1)
    expect(drawer.querySelector('[data-testid="cart-total"]')!.textContent).toMatch(/49\.80/)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(useCartStore().isOpen).toBe(false)
    wrapper.unmount()
  })

  it('protege las rutas de administración redirigiendo al login', async () => {
    const { router, wrapper } = await mountWithApp(App, '/admin/productos')
    expect(router.currentRoute.value.name).toBe('admin-login')
    expect(router.currentRoute.value.query.redirect).toBe('/admin/productos')
    wrapper.unmount()
  })

  it('la página de inicio carga destacados y es accesible', async () => {
    const { wrapper } = await mountWithApp(HomeView, '/')
    expect(wrapper.text()).toContain('Protector solar')
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(await axeViolations(wrapper.element)).toEqual([])
    wrapper.unmount()
  })
})
