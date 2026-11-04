<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next'
import ModalDialog from '@/components/admin/ModalDialog.vue'
import ProductForm from '@/components/admin/ProductForm.vue'
import ProductIllustration from '@/components/ProductIllustration.vue'
import StockBadge from '@/components/StockBadge.vue'
import { adminApi } from '@/api/admin'
import { storeApi } from '@/api/store'
import { ApiError } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import type { AdminProduct, Category, PageMeta } from '@/types'
import { formatMoney } from '@/utils/format'

const toast = useToastStore()
const products = ref<AdminProduct[]>([])
const meta = ref<PageMeta | null>(null)
const categories = ref<Category[]>([])
const loading = ref(true)
const q = ref('')
const active = ref<'' | '1' | '0'>('')
const page = ref(1)
const editing = ref<AdminProduct | null>(null)
const formOpen = ref(false)
const toDelete = ref<AdminProduct | null>(null)
const deleting = ref(false)
let debounce: ReturnType<typeof setTimeout> | undefined

async function load() {
  loading.value = true
  try {
    const result = await adminApi.products({ q: q.value || undefined, active: active.value === '' ? null : active.value === '1', page: page.value, per_page: 15 })
    products.value = result.data
    meta.value = result.meta
  } catch (e) {
    toast.error('No se pudieron cargar los productos', e instanceof ApiError ? e.message : undefined)
  } finally {
    loading.value = false
  }
}

function onSearch() {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
}

function openCreate() {
  editing.value = null
  formOpen.value = true
}
function openEdit(product: AdminProduct) {
  editing.value = product
  formOpen.value = true
}
function onSaved() {
  formOpen.value = false
  load()
}

async function toggleActive(product: AdminProduct) {
  try {
    const updated = await adminApi.updateProduct(product.id, { is_active: !product.is_active })
    Object.assign(product, updated)
    toast.success(updated.is_active ? 'Producto visible en la tienda' : 'Producto ocultado', updated.name)
  } catch (e) {
    toast.error('No se pudo actualizar', e instanceof ApiError ? e.message : undefined)
  }
}

async function confirmDelete() {
  if (!toDelete.value) return
  deleting.value = true
  try {
    await adminApi.deleteProduct(toDelete.value.id)
    toast.success('Producto eliminado', toDelete.value.name)
    toDelete.value = null
    load()
  } catch (e) {
    toast.error('No se pudo eliminar', e instanceof ApiError ? e.message : undefined)
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  load()
  categories.value = await storeApi.categories().catch(() => [])
})
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-3xl">Productos</h1>
        <p class="text-sm text-ink-500">{{ meta?.total ?? '…' }} productos ficticios</p>
      </div>
      <button type="button" class="btn-primary" data-testid="new-product" @click="openCreate"><Plus class="h-4 w-4" aria-hidden="true" /> Nuevo producto</button>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
      <div class="relative w-full sm:w-72">
        <label for="admin-product-search" class="sr-only">Buscar productos</label>
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" aria-hidden="true" />
        <input id="admin-product-search" v-model="q" type="search" class="field pl-9" placeholder="Nombre, marca o SKU" maxlength="80" @input="onSearch" />
      </div>
      <label for="admin-product-active" class="sr-only">Visibilidad</label>
      <select id="admin-product-active" v-model="active" class="field w-auto" @change="page = 1; load()">
        <option value="">Todos</option>
        <option value="1">Visibles</option>
        <option value="0">Ocultos</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full min-w-[760px] text-sm">
        <caption class="sr-only">Listado de productos</caption>
        <thead class="bg-cream-50 text-left text-xs uppercase tracking-wider text-ink-400">
          <tr>
            <th scope="col" class="px-4 py-3 font-medium">Producto</th>
            <th scope="col" class="px-4 py-3 font-medium">Categoría</th>
            <th scope="col" class="px-4 py-3 text-right font-medium">Precio</th>
            <th scope="col" class="px-4 py-3 font-medium">Stock</th>
            <th scope="col" class="px-4 py-3 font-medium">Visible</th>
            <th scope="col" class="px-4 py-3 text-right font-medium"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="n in 6" :key="n" class="border-t border-cream-100"><td colspan="6" class="px-4 py-3"><div class="skeleton h-8 w-full" /></td></tr>
          </template>
          <tr v-for="product in products" v-else :key="product.id" class="border-t border-cream-100 hover:bg-cream-50/60" data-testid="admin-product-row">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg"><ProductIllustration :kind="product.illustration" :image-url="product.image_url" :alt="product.name" size="sm" /></div>
                <div class="min-w-0">
                  <p class="truncate font-medium">{{ product.name }}</p>
                  <p class="text-xs text-ink-400">{{ product.sku }} · {{ product.brand }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 text-ink-500">{{ product.category?.name }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatMoney(product.price_cents) }}</td>
            <td class="px-4 py-3"><StockBadge :status="product.stock_status" :stock="product.stock" /> <span class="ml-1 text-xs text-ink-400">{{ product.stock }} u.</span></td>
            <td class="px-4 py-3">
              <button
                type="button"
                role="switch"
                :aria-checked="product.is_active"
                :aria-label="`Visibilidad de ${product.name}`"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                :class="product.is_active ? 'bg-sage-500' : 'bg-cream-300'"
                @click="toggleActive(product)"
              >
                <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="product.is_active ? 'translate-x-5' : 'translate-x-0.5'" />
              </button>
            </td>
            <td class="px-4 py-3 text-right">
              <button type="button" class="btn-icon" :aria-label="`Editar ${product.name}`" @click="openEdit(product)"><Pencil class="h-4 w-4" aria-hidden="true" /></button>
              <button type="button" class="btn-icon text-clay-700" :aria-label="`Eliminar ${product.name}`" @click="toDelete = product"><Trash2 class="h-4 w-4" aria-hidden="true" /></button>
            </td>
          </tr>
          <tr v-if="!loading && products.length === 0"><td colspan="6" class="px-4 py-10 text-center text-ink-400">Sin productos para esos filtros.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-end gap-2 text-sm">
      <button type="button" class="btn-ghost" :disabled="page <= 1" @click="page--; load()">Anterior</button>
      <span class="text-ink-500">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
      <button type="button" class="btn-ghost" :disabled="page >= meta.last_page" @click="page++; load()">Siguiente</button>
    </div>

    <ModalDialog :open="formOpen" :title="editing ? 'Editar producto' : 'Nuevo producto'" size="lg" @close="formOpen = false">
      <ProductForm :product="editing" :categories="categories" @saved="onSaved" @cancel="formOpen = false" />
    </ModalDialog>

    <ModalDialog :open="!!toDelete" title="Eliminar producto" @close="toDelete = null">
      <p class="text-sm text-ink-700">¿Eliminar <strong>{{ toDelete?.name }}</strong>? Si tiene pedidos asociados, la API lo impedirá y podrás ocultarlo en su lugar.</p>
      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="btn-ghost" @click="toDelete = null">Cancelar</button>
        <button type="button" class="btn-danger" :disabled="deleting" @click="confirmDelete">{{ deleting ? 'Eliminando…' : 'Eliminar' }}</button>
      </div>
    </ModalDialog>
  </div>
</template>
