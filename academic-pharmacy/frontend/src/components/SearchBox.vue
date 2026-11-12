<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search } from 'lucide-vue-next'
import ProductIllustration from './ProductIllustration.vue'
import { storeApi } from '@/api/store'
import { formatMoney } from '@/utils/format'
import type { Product } from '@/types'

/**
 * Buscador principal con sugerencias en vivo (combobox ARIA). Consulta la
 * API real (GET /products?q=) con debounce y cancela peticiones obsoletas.
 * Enter sin sugerencia seleccionada abre el catálogo filtrado.
 */
const props = withDefaults(defineProps<{ inputId: string; compact?: boolean }>(), { compact: false })

const route = useRoute()
const router = useRouter()
const term = ref(typeof route.query.q === 'string' ? route.query.q : '')
const suggestions = ref<Product[]>([])
const open = ref(false)
const active = ref(-1)
const loading = ref(false)
let controller: AbortController | null = null
let timer: ReturnType<typeof setTimeout> | undefined

const listId = computed(() => `${props.inputId}-sugerencias`)

watch(
  () => route.query.q,
  (q) => {
    term.value = typeof q === 'string' ? q : ''
  },
)

function close() {
  open.value = false
  active.value = -1
}

function onInput() {
  clearTimeout(timer)
  const q = term.value.trim()
  if (q.length < 2) {
    suggestions.value = []
    close()
    return
  }
  timer = setTimeout(async () => {
    controller?.abort()
    controller = new AbortController()
    loading.value = true
    try {
      const result = await storeApi.products({ q, per_page: 5 }, controller.signal)
      suggestions.value = result.data
      open.value = true
      active.value = -1
    } catch (e) {
      if ((e as Error).name !== 'AbortError') suggestions.value = []
    } finally {
      loading.value = false
    }
  }, 250)
}

function goToProduct(product: Product) {
  close()
  router.push({ name: 'product', params: { slug: product.slug } })
}

function submit() {
  if (open.value && active.value >= 0 && suggestions.value[active.value]) {
    goToProduct(suggestions.value[active.value])
    return
  }
  close()
  const q = term.value.trim()
  router.push({ name: 'catalog', query: q ? { q } : {} })
}

function onKeydown(event: KeyboardEvent) {
  if (!open.value || suggestions.value.length === 0) return
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    active.value = (active.value + 1) % suggestions.value.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    active.value = active.value <= 0 ? suggestions.value.length - 1 : active.value - 1
  } else if (event.key === 'Escape') {
    close()
  }
}

function onBlur() {
  // Deja terminar el clic en una sugerencia antes de cerrar.
  setTimeout(close, 150)
}

onBeforeUnmount(() => {
  clearTimeout(timer)
  controller?.abort()
})
</script>

<template>
  <form class="relative w-full" role="search" @submit.prevent="submit">
    <label :for="inputId" class="sr-only">Buscar productos</label>
    <div class="relative flex items-center">
      <Search class="pointer-events-none absolute left-4 h-5 w-5 text-ink-400" aria-hidden="true" />
      <input
        :id="inputId"
        v-model="term"
        type="search"
        maxlength="80"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open"
        :aria-controls="listId"
        :aria-activedescendant="active >= 0 ? `${listId}-${active}` : undefined"
        placeholder="¿Qué estás buscando? Vitaminas, protector solar, curitas…"
        class="block w-full rounded-full border-2 border-clinic-100 bg-white py-3 pl-12 pr-28 text-[15px] text-ink-900 shadow-soft placeholder:text-ink-400 transition-[border-color,box-shadow] focus:border-clinic-500 focus:outline-none focus:ring-4 focus:ring-clinic-100"
        :class="compact ? 'py-2.5' : ''"
        :data-testid="inputId"
        @input="onInput"
        @keydown="onKeydown"
        @focus="suggestions.length && term.trim().length >= 2 && (open = true)"
        @blur="onBlur"
      />
      <button
        type="submit"
        class="absolute right-1.5 inline-flex items-center gap-1.5 rounded-full bg-clinic-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-clinic-700"
      >
        Buscar
      </button>
    </div>

    <Transition name="fade">
      <ul
        v-if="open"
        :id="listId"
        role="listbox"
        aria-label="Sugerencias de productos"
        class="absolute left-0 right-0 top-full z-40 mt-2 overflow-hidden rounded-2xl border border-sage-100 bg-white p-1.5 shadow-lift"
      >
        <li v-if="suggestions.length === 0" class="px-4 py-3 text-sm text-ink-500" role="option" aria-disabled="true" aria-selected="false">
          Sin coincidencias para «{{ term.trim() }}».
        </li>
        <li
          v-for="(product, index) in suggestions"
          :id="`${listId}-${index}`"
          :key="product.id"
          role="option"
          :aria-selected="index === active"
          class="flex cursor-pointer items-center gap-3 rounded-xl px-2.5 py-2 transition-colors"
          :class="index === active ? 'bg-clinic-50' : 'hover:bg-sage-50'"
          @mousedown.prevent="goToProduct(product)"
        >
          <span class="h-11 w-11 shrink-0 overflow-hidden rounded-lg" aria-hidden="true">
            <ProductIllustration :kind="product.illustration" :image-url="product.image_url" :alt="''" size="sm" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-ink-900">{{ product.name }}</span>
            <span class="block truncate text-xs text-ink-500">{{ product.brand }} · {{ product.presentation }}</span>
          </span>
          <span class="text-sm font-bold text-clinic-700">{{ formatMoney(product.price_cents) }}</span>
        </li>
        <li v-if="suggestions.length" role="presentation" class="border-t border-sage-50 px-3 pb-1 pt-2 text-xs text-ink-500">
          Pulsa Enter para ver todos los resultados
        </li>
      </ul>
    </Transition>
  </form>
</template>
