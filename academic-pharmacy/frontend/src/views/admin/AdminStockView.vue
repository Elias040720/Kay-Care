<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ArrowDownRight, ArrowUpRight, Search } from 'lucide-vue-next'
import ModalDialog from '@/components/admin/ModalDialog.vue'
import StockBadge from '@/components/StockBadge.vue'
import { adminApi } from '@/api/admin'
import { ApiError } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import type { AdminProduct, StockMovement } from '@/types'
import { formatDate } from '@/utils/format'

const toast = useToastStore()
const products = ref<AdminProduct[]>([])
const movements = ref<StockMovement[]>([])
const loading = ref(true)
const lowOnly = ref(false)
const q = ref('')
const target = ref<AdminProduct | null>(null)
const adjustment = reactive({ direction: 'in' as 'in' | 'out', amount: 1, reason: 'restock' as 'restock' | 'adjustment', note: '' })
const saving = ref(false)
const adjustError = ref<string | null>(null)

const REASONS: Record<StockMovement['reason'], string> = {
  checkout: 'Venta simulada',
  adjustment: 'Ajuste',
  restock: 'Reposición',
  cancellation: 'Cancelación',
  seed: 'Carga demo',
}

const delta = computed(() => (adjustment.direction === 'in' ? 1 : -1) * Math.floor(Number(adjustment.amount) || 0))
const resulting = computed(() => (target.value ? target.value.stock + delta.value : 0))

async function load() {
  loading.value = true
  try {
    const [p, m] = await Promise.all([
      adminApi.products({ q: q.value || undefined, low_stock: lowOnly.value || undefined, sort: 'stock_desc', per_page: 48 }),
      adminApi.movements({ per_page: 15 }),
    ])
    products.value = [...p.data].sort((a, b) => a.stock - b.stock)
    movements.value = m.data
  } catch (e) {
    toast.error('No se pudo cargar el inventario', e instanceof ApiError ? e.message : undefined)
  } finally {
    loading.value = false
  }
}

function openAdjust(product: AdminProduct) {
  target.value = product
  Object.assign(adjustment, { direction: 'in', amount: 1, reason: 'restock', note: '' })
  adjustError.value = null
}

async function submitAdjust() {
  if (!target.value) return
  adjustError.value = null
  if (!Number.isInteger(Number(adjustment.amount)) || Number(adjustment.amount) < 1) {
    adjustError.value = 'La cantidad debe ser un entero mayor que cero.'
    return
  }
  if (resulting.value < 0) {
    adjustError.value = 'El ajuste dejaría el stock en negativo.'
    return
  }
  saving.value = true
  try {
    const updated = await adminApi.adjustStock(target.value.id, delta.value, adjustment.reason, adjustment.note)
    toast.success('Stock actualizado', `${updated.name}: ${updated.stock} unidades`)
    target.value = null
    load()
  } catch (e) {
    adjustError.value = e instanceof ApiError ? (e.firstFieldError('delta') ?? e.message) : 'No se pudo ajustar el stock.'
  } finally {
    saving.value = false
  }
}

let debounce: ReturnType<typeof setTimeout> | undefined
function onSearch() {
  clearTimeout(debounce)
  debounce = setTimeout(load, 300)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-3xl">Gestión de stock</h1>
      <p class="text-sm text-ink-500">Cada ajuste queda registrado con su motivo y responsable.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
      <section class="card overflow-hidden" aria-labelledby="inventory-title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-cream-200 p-4">
          <h2 id="inventory-title" class="font-sans text-sm font-semibold">Inventario</h2>
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
              <label for="stock-search" class="sr-only">Buscar producto</label>
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" aria-hidden="true" />
              <input id="stock-search" v-model="q" type="search" class="field w-56 py-2 pl-9" placeholder="Buscar" maxlength="80" @input="onSearch" />
            </div>
            <label class="flex items-center gap-2 text-sm"><input v-model="lowOnly" type="checkbox" class="h-4 w-4 accent-sage-600" @change="load" /> Solo stock bajo</label>
          </div>
        </div>
        <ul class="max-h-[560px] divide-y divide-cream-100 overflow-y-auto">
          <template v-if="loading"><li v-for="n in 6" :key="n" class="p-4"><div class="skeleton h-8" /></li></template>
          <li v-for="product in products" v-else :key="product.id" class="flex items-center justify-between gap-3 px-4 py-3" data-testid="stock-row">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium">{{ product.name }}</p>
              <p class="text-xs text-ink-400">{{ product.sku }} · alerta ≤ {{ product.low_stock_threshold }}</p>
            </div>
            <div class="flex items-center gap-3">
              <StockBadge :status="product.stock_status" :stock="product.stock" />
              <span class="w-12 text-right text-sm font-semibold">{{ product.stock }}</span>
              <button type="button" class="btn-ghost px-3 py-1.5 text-xs" :data-testid="`adjust-${product.sku}`" @click="openAdjust(product)">Ajustar</button>
            </div>
          </li>
          <li v-if="!loading && products.length === 0" class="p-8 text-center text-sm text-ink-400">Sin productos.</li>
        </ul>
      </section>

      <section class="card overflow-hidden" aria-labelledby="movements-title">
        <h2 id="movements-title" class="border-b border-cream-200 p-4 font-sans text-sm font-semibold">Últimos movimientos</h2>
        <ul class="max-h-[560px] divide-y divide-cream-100 overflow-y-auto">
          <li v-for="m in movements" :key="m.id" class="flex items-start gap-3 px-4 py-3 text-sm">
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full" :class="m.delta > 0 ? 'bg-sage-100 text-sage-700' : 'bg-clay-100 text-clay-700'">
              <component :is="m.delta > 0 ? ArrowUpRight : ArrowDownRight" class="h-4 w-4" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-medium">{{ m.product?.name ?? 'Producto eliminado' }}</p>
              <p class="text-xs text-ink-400">{{ REASONS[m.reason] }}<template v-if="m.reference"> · {{ m.reference }}</template><template v-if="m.admin"> · {{ m.admin }}</template></p>
              <p class="text-xs text-ink-400">{{ formatDate(m.created_at) }}</p>
            </div>
            <div class="text-right">
              <p class="font-semibold" :class="m.delta > 0 ? 'text-sage-700' : 'text-clay-700'">{{ m.delta > 0 ? '+' : '' }}{{ m.delta }}</p>
              <p class="text-xs text-ink-400">→ {{ m.stock_after }}</p>
            </div>
          </li>
          <li v-if="!loading && movements.length === 0" class="p-8 text-center text-sm text-ink-400">Sin movimientos.</li>
        </ul>
      </section>
    </div>

    <ModalDialog :open="!!target" :title="`Ajustar stock · ${target?.name ?? ''}`" @close="target = null">
      <form class="space-y-4" novalidate @submit.prevent="submitAdjust">
        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Tipo de movimiento">
          <label class="cursor-pointer rounded-xl border px-3 py-2 text-center text-sm" :class="adjustment.direction === 'in' ? 'border-sage-400 bg-sage-50 text-sage-700' : 'border-cream-300'">
            <input v-model="adjustment.direction" type="radio" value="in" class="sr-only" @change="adjustment.reason = 'restock'" /> Entrada
          </label>
          <label class="cursor-pointer rounded-xl border px-3 py-2 text-center text-sm" :class="adjustment.direction === 'out' ? 'border-clay-500 bg-clay-100 text-clay-700' : 'border-cream-300'">
            <input v-model="adjustment.direction" type="radio" value="out" class="sr-only" @change="adjustment.reason = 'adjustment'" /> Salida
          </label>
        </div>
        <div>
          <label for="adj-amount" class="field-label">Cantidad</label>
          <input id="adj-amount" v-model.number="adjustment.amount" type="number" min="1" max="1000" step="1" class="field" data-testid="adjust-amount" />
        </div>
        <div>
          <label for="adj-reason" class="field-label">Motivo</label>
          <select id="adj-reason" v-model="adjustment.reason" class="field">
            <option value="restock">Reposición</option>
            <option value="adjustment">Ajuste de inventario</option>
          </select>
        </div>
        <div>
          <label for="adj-note" class="field-label">Nota (opcional)</label>
          <input id="adj-note" v-model="adjustment.note" class="field" maxlength="200" />
        </div>
        <p class="rounded-xl bg-cream-100 px-3 py-2 text-sm text-ink-500">Stock resultante: <strong :class="resulting < 0 ? 'text-clay-700' : 'text-ink-900'">{{ resulting }}</strong></p>
        <p v-if="adjustError" class="field-error" role="alert">{{ adjustError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-ghost" @click="target = null">Cancelar</button>
          <button type="submit" class="btn-primary" :disabled="saving" data-testid="adjust-submit">{{ saving ? 'Guardando…' : 'Registrar movimiento' }}</button>
        </div>
      </form>
    </ModalDialog>
  </div>
</template>
