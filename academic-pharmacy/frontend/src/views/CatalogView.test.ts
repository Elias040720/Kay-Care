import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import CatalogView from './CatalogView.vue'
import { axeViolations, categories, makeProduct, mountWithApp, page } from '@/test/helpers'

vi.mock('@/api/store', () => ({
  storeApi: { products: vi.fn(), categories: vi.fn() },
}))
import { storeApi } from '@/api/store'

const NOTICE = 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES'

describe('CatalogView', () => {
  beforeEach(() => {
    vi.mocked(storeApi.categories).mockResolvedValue(categories)
    vi.mocked(storeApi.products).mockResolvedValue(page([makeProduct({ id: 1, name: 'Vitamina C' }), makeProduct({ id: 2, name: 'Omega 3' })]))
  })
  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('muestra skeletons mientras carga y luego los productos', async () => {
    let resolve!: (v: ReturnType<typeof page>) => void
    vi.mocked(storeApi.products).mockReturnValueOnce(new Promise((r) => (resolve = r as typeof resolve)) as never)
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo')

    expect(wrapper.findAll('[data-testid="skeleton-card"]').length).toBeGreaterThan(0)
    resolve(page([makeProduct({ name: 'Vitamina C' })]))
    await flushPromises()
    expect(wrapper.findAll('[data-testid="skeleton-card"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="product-card"]')).toHaveLength(1)
    wrapper.unmount()
  })

  it('muestra el aviso académico y el total de resultados', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo')
    expect(wrapper.text()).toContain(NOTICE)
    expect(wrapper.get('[data-testid="results-count"]').text()).toContain('2 productos')
    wrapper.unmount()
  })

  it('lee filtros de la URL y los envía a la API', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo?q=sol&categoria=cuidado-de-la-piel&orden=price_desc&min=5&max=60&stock=1')
    expect(storeApi.products).toHaveBeenLastCalledWith(
      expect.objectContaining({ q: 'sol', category: 'cuidado-de-la-piel', sort: 'price_desc', min_price: 5, max_price: 60, in_stock: true, page: 1 }),
      expect.any(AbortSignal),
    )
    wrapper.unmount()
  })

  it('filtra ofertas desde la URL y cambia el título', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo?ofertas=1')
    expect(storeApi.products).toHaveBeenLastCalledWith(expect.objectContaining({ on_sale: true }), expect.any(AbortSignal))
    expect(wrapper.get('[data-testid="catalog-heading"]').text()).toBe('Ofertas de la semana')
    wrapper.unmount()
  })

  it('muestra el nombre de la categoría seleccionada como título', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo?categoria=cuidado-de-la-piel')
    await flushPromises()
    expect(wrapper.get('[data-testid="catalog-heading"]').text()).toBe('Cuidado de la piel')
    wrapper.unmount()
  })

  it('ignora parámetros de URL inválidos', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo?orden=drop&categoria=<script>&min=abc')
    expect(storeApi.products).toHaveBeenLastCalledWith(
      expect.objectContaining({ sort: 'featured', category: undefined, min_price: undefined }),
      expect.any(AbortSignal),
    )
    wrapper.unmount()
  })

  it('busca con debounce y actualiza la URL', async () => {
    vi.useFakeTimers()
    const { wrapper, router } = await mountWithApp(CatalogView, '/catalogo')
    vi.mocked(storeApi.products).mockClear()

    const input = wrapper.get('[data-testid="catalog-search"]')
    await input.setValue('omega')
    expect(storeApi.products).not.toHaveBeenCalled()
    vi.advanceTimersByTime(320)
    await flushPromises()

    expect(storeApi.products).toHaveBeenCalledTimes(1)
    expect(storeApi.products).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'omega' }), expect.any(AbortSignal))
    expect(router.currentRoute.value.query.q).toBe('omega')
    wrapper.unmount()
  })

  it('filtra por categoría, stock y ordena', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo')

    await wrapper.get('[data-testid="filter-category-bienestar-diario"]').trigger('change')
    expect(storeApi.products).toHaveBeenLastCalledWith(expect.objectContaining({ category: 'bienestar-diario' }), expect.any(AbortSignal))

    const stock = wrapper.get('[data-testid="filter-in-stock"]')
    ;(stock.element as HTMLInputElement).checked = true
    await stock.trigger('change')
    expect(storeApi.products).toHaveBeenLastCalledWith(expect.objectContaining({ in_stock: true }), expect.any(AbortSignal))

    await wrapper.get('[data-testid="catalog-sort"]').setValue('price_asc')
    expect(storeApi.products).toHaveBeenLastCalledWith(expect.objectContaining({ sort: 'price_asc', page: 1 }), expect.any(AbortSignal))
    wrapper.unmount()
  })

  it('muestra estado vacío con acción para limpiar filtros', async () => {
    vi.mocked(storeApi.products).mockResolvedValue(page([]))
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo?q=zzz')
    expect(wrapper.text()).toContain('Sin resultados')

    vi.mocked(storeApi.products).mockResolvedValue(page([makeProduct()]))
    const clear = wrapper.findAll('button').find((b) => b.text() === 'Limpiar filtros')!
    await clear.trigger('click')
    await flushPromises()
    expect(storeApi.products).toHaveBeenLastCalledWith(expect.objectContaining({ q: undefined }), expect.any(AbortSignal))
    expect(wrapper.findAll('[data-testid="product-card"]')).toHaveLength(1)
    wrapper.unmount()
  })

  it('muestra un error recuperable si la API falla', async () => {
    vi.mocked(storeApi.products).mockRejectedValueOnce(new Error('boom'))
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo')
    expect(wrapper.text()).toContain('No se pudo cargar el catálogo')
    expect(wrapper.findAll('button').some((b) => b.text() === 'Reintentar')).toBe(true)
    wrapper.unmount()
  })

  it('no tiene violaciones básicas de accesibilidad', async () => {
    const { wrapper } = await mountWithApp(CatalogView, '/catalogo')
    expect(await axeViolations(wrapper.element)).toEqual([])
    wrapper.unmount()
  })
})
