import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import SearchBox from './SearchBox.vue'
import { makeProduct, mountWithApp, page } from '@/test/helpers'

vi.mock('@/api/store', () => ({
  storeApi: { products: vi.fn() },
}))
import { storeApi } from '@/api/store'

describe('SearchBox', () => {
  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('consulta la API con debounce y muestra sugerencias accesibles', async () => {
    vi.useFakeTimers()
    vi.mocked(storeApi.products).mockResolvedValue(page([makeProduct({ id: 5, name: 'Protector solar FPS 50+', slug: 'protector-solar' })]))
    const { wrapper } = await mountWithApp(SearchBox, '/', { props: { inputId: 'buscar-test' } })
    const input = wrapper.get('input')
    await input.setValue('prot')
    await input.trigger('input')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(storeApi.products).toHaveBeenCalledWith({ q: 'prot', per_page: 5 }, expect.any(AbortSignal))
    expect(input.attributes('aria-expanded')).toBe('true')
    expect(wrapper.findAll('[role="option"]')[0].text()).toContain('Protector solar FPS 50+')
    wrapper.unmount()
  })

  it('al enviar abre el catálogo con el término buscado', async () => {
    const { wrapper, router } = await mountWithApp(SearchBox, '/', { props: { inputId: 'buscar-test-2' } })
    const push = vi.spyOn(router, 'push')
    try {
      await wrapper.get('input').setValue('  vitamina  ')
      await wrapper.get('form').trigger('submit')
      expect(push).toHaveBeenCalledWith({ name: 'catalog', query: { q: 'vitamina' } })
    } finally {
      wrapper.unmount()
    }
  })

  it('no consulta la API con menos de 2 caracteres', async () => {
    vi.mocked(storeApi.products).mockClear()
    const { wrapper } = await mountWithApp(SearchBox, '/', { props: { inputId: 'buscar-test-3' } })
    await wrapper.get('input').setValue('a')
    await wrapper.get('input').trigger('input')
    await new Promise((r) => setTimeout(r, 300))
    expect(storeApi.products).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
