import { defineStore } from 'pinia'
import { ref } from 'vue'
import { storeApi } from '@/api/store'
import type { Category } from '@/types'

/**
 * Categorías compartidas por el header, el inicio y el catálogo. Se piden
 * una sola vez a la API (GET /categories) y se reutilizan.
 */
export const useCatalogStore = defineStore('catalog', () => {
  const categories = ref<Category[]>([])
  const loaded = ref(false)
  const failed = ref(false)
  let pending: Promise<void> | null = null

  function loadCategories(force = false): Promise<void> {
    if (loaded.value && !force) return Promise.resolve()
    if (pending) return pending
    failed.value = false
    pending = storeApi
      .categories()
      .then((data) => {
        categories.value = data
        loaded.value = true
      })
      .catch(() => {
        failed.value = true
      })
      .finally(() => {
        pending = null
      })
    return pending
  }

  function bySlug(slug: string): Category | undefined {
    return categories.value.find((c) => c.slug === slug)
  }

  return { categories, loaded, failed, loadCategories, bySlug }
})
