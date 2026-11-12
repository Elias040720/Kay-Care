import { describe, expect, it } from 'vitest'
import ProductCard from './ProductCard.vue'
import DemoBanner from './DemoBanner.vue'
import { makeProduct, mountWithApp } from '@/test/helpers'
import { useCartStore } from '@/stores/cart'

describe('ProductCard', () => {
  it('muestra nombre, marca, precio y estado de stock', async () => {
    const product = makeProduct({ name: 'Gel de aloe vera calmante', brand: 'Verdal', price_cents: 1650, stock: 3, stock_status: 'low_stock' })
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product } })

    expect(wrapper.text()).toContain('Gel de aloe vera calmante')
    expect(wrapper.text()).toContain('Verdal')
    expect(wrapper.get('[data-testid="product-price"]').text()).toMatch(/16\.50/)
    expect(wrapper.get('[data-testid="stock-badge"]').text()).toContain('Quedan 3')
    wrapper.unmount()
  })

  it('añade al carrito al pulsar el botón', async () => {
    const product = makeProduct({ id: 77 })
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product } })
    await wrapper.get('[data-testid="add-to-cart"]').trigger('click')
    expect(useCartStore().count).toBe(1)
    wrapper.unmount()
  })

  it('deshabilita el botón cuando está agotado', async () => {
    const product = makeProduct({ stock: 0, stock_status: 'out_of_stock' })
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product } })
    const button = wrapper.get('[data-testid="add-to-cart"]')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-label')).toContain('Agotado')
    wrapper.unmount()
  })

  it('muestra la etiqueta de promoción y el precio anterior cuando hay descuento', async () => {
    const product = makeProduct({ price_cents: 4990, compare_at_price_cents: 5890, discount_percent: 15 })
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product } })
    expect(wrapper.get('[data-testid="promo-badge"]').text()).toBe('-15%')
    expect(wrapper.find('s').text()).toMatch(/58.90/)
    wrapper.unmount()
  })

  it('no muestra promoción si el producto no tiene descuento', async () => {
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product: makeProduct() } })
    expect(wrapper.find('[data-testid="promo-badge"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('da feedback en el botón tras agregar', async () => {
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product: makeProduct({ id: 78 }) } })
    const button = wrapper.get('[data-testid="add-to-cart"]')
    await button.trigger('click')
    expect(button.text()).toContain('Agregado')
    wrapper.unmount()
  })

  it('renderiza nombres con HTML como texto, sin interpretarlo (XSS)', async () => {
    const product = makeProduct({ name: '<img src=x onerror=alert(1)>' })
    const { wrapper } = await mountWithApp(ProductCard, '/', { props: { product } })
    expect(wrapper.find('img[src="x"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>')
    wrapper.unmount()
  })
})

describe('DemoBanner', () => {
  it('muestra el aviso académico literal', async () => {
    const { wrapper } = await mountWithApp(DemoBanner)
    expect(wrapper.text()).toContain('PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES')
    wrapper.unmount()
  })
})
