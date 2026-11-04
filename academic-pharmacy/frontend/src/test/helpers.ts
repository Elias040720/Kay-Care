import { mount, flushPromises, type MountingOptions } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import type { Component } from 'vue'
import axe from 'axe-core'
import { routes, installGuards } from '@/router'
import type { Category, Product } from '@/types'

export function makeProduct(overrides: Partial<Product> = {}): Product {
  const id = overrides.id ?? Math.floor(Math.random() * 100000)
  return {
    id,
    sku: `KCF-T-${id}`,
    name: `Producto Demo ${id}`,
    slug: `producto-demo-${id}`,
    brand: 'Verdal',
    short_description: 'Producto ficticio.',
    description: 'Descripción ficticia.',
    presentation: 'Caja x 10',
    price_cents: 1990,
    stock: 10,
    stock_status: 'in_stock',
    image_url: null,
    illustration: 'capsule',
    is_featured: false,
    category: { id: 1, name: 'Bienestar diario', slug: 'bienestar-diario', description: null },
    ...overrides,
  }
}

export const categories: Category[] = [
  { id: 1, name: 'Bienestar diario', slug: 'bienestar-diario', description: null, products_count: 4 },
  { id: 2, name: 'Cuidado de la piel', slug: 'cuidado-de-la-piel', description: null, products_count: 4 },
]

export function page<T>(data: T[], total = data.length) {
  return { data, meta: { current_page: 1, last_page: Math.max(1, Math.ceil(total / 12)), per_page: 12, total } }
}

export async function mountWithApp(component: Component, path = '/', options: MountingOptions<unknown> = {}): Promise<{ wrapper: ReturnType<typeof mount>; router: Router; pinia: Pinia }> {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = installGuards(createRouter({ history: createMemoryHistory(), routes }))
  await router.push(path)
  await router.isReady()
  const wrapper = mount(component, {
    attachTo: document.body,
    ...options,
    global: { plugins: [pinia, router], ...(options.global ?? {}) },
  })
  await flushPromises()
  return { wrapper, router, pinia }
}

/** Ejecuta axe-core sobre el DOM renderizado (sin contraste: jsdom no calcula estilos). */
export async function axeViolations(element: Element) {
  const result = await axe.run(element, {
    rules: { 'color-contrast': { enabled: false }, region: { enabled: false } },
  })
  return result.violations.map((v) => `${v.id}: ${v.help} (${v.nodes.length})`)
}
