<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ImageUp } from 'lucide-vue-next'
import ProductIllustration from '@/components/ProductIllustration.vue'
import { adminApi, type ProductPayload } from '@/api/admin'
import { ApiError } from '@/api/client'
import type { AdminProduct, Category, Illustration } from '@/types'
import { useToastStore } from '@/stores/toast'

const props = defineProps<{ product: AdminProduct | null; categories: Category[] }>()
const emit = defineEmits<{ saved: [product: AdminProduct]; cancel: [] }>()
const toast = useToastStore()

const ILLUSTRATIONS: Illustration[] = ['capsule', 'bottle', 'drops', 'tube', 'box', 'spray', 'sachet', 'jar']
const MAX_IMAGE_BYTES = 2 * 1024 * 1024
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const isEdit = computed(() => props.product !== null)
const form = reactive<ProductPayload>(blank())
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const imageFile = ref<File | null>(null)
const imageError = ref<string | null>(null)

function blank(): ProductPayload {
  return {
    category_id: props.categories[0]?.id ?? 0,
    sku: '',
    name: '',
    short_description: '',
    description: '',
    presentation: '',
    brand: '',
    price: 0,
    compare_at_price: null,
    stock: 0,
    low_stock_threshold: 5,
    illustration: 'capsule',
    is_active: true,
    is_featured: false,
  }
}

watch(
  () => props.product,
  (p) => {
    errors.value = {}
    imageFile.value = null
    imageError.value = null
    Object.assign(form, blank(), p ? {
      category_id: p.category_id, sku: p.sku, name: p.name, short_description: p.short_description,
      description: p.description, presentation: p.presentation, brand: p.brand, price: p.price_cents / 100,
      compare_at_price: p.compare_at_price_cents ? p.compare_at_price_cents / 100 : null,
      low_stock_threshold: p.low_stock_threshold, illustration: p.illustration, is_active: p.is_active, is_featured: p.is_featured,
    } : {})
  },
  { immediate: true },
)

function err(field: string) {
  return errors.value[field]?.[0]
}

function onImage(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  imageError.value = null
  imageFile.value = null
  if (!file) return
  // Validación previa en el cliente; el servidor vuelve a validar por contenido.
  if (!ALLOWED_TYPES.includes(file.type)) {
    imageError.value = 'Formato no permitido: usa JPG, PNG o WebP.'
    return
  }
  if (file.size > MAX_IMAGE_BYTES) {
    imageError.value = 'La imagen supera 2 MB.'
    return
  }
  imageFile.value = file
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    const compareAt = form.compare_at_price === null || String(form.compare_at_price) === '' ? null : Number(form.compare_at_price)
    const payload: Partial<ProductPayload> = { ...form, price: Number(form.price), compare_at_price: compareAt }
    if (isEdit.value) delete payload.stock
    let saved = isEdit.value
      ? await adminApi.updateProduct(props.product!.id, payload)
      : await adminApi.createProduct(payload as ProductPayload)

    if (imageFile.value) {
      try {
        saved = await adminApi.uploadImage(saved.id, imageFile.value)
      } catch (e) {
        toast.error('Producto guardado, pero la imagen fue rechazada', e instanceof ApiError ? (e.firstFieldError('image') ?? e.message) : undefined)
      }
    }
    toast.success(isEdit.value ? 'Producto actualizado' : 'Producto creado', saved.name)
    emit('saved', saved)
  } catch (e) {
    if (e instanceof ApiError && e.code === 'validation_failed') {
      errors.value = e.errors
    } else {
      toast.error('No se pudo guardar', e instanceof ApiError ? e.message : undefined)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <form class="space-y-4" novalidate data-testid="product-form" @submit.prevent="save">
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label for="pf-name" class="field-label">Nombre</label>
        <input id="pf-name" v-model="form.name" class="field" maxlength="120" required />
        <p v-if="err('name') || err('slug')" class="field-error">{{ err('name') ?? err('slug') }}</p>
      </div>
      <div>
        <label for="pf-sku" class="field-label">SKU</label>
        <input id="pf-sku" v-model="form.sku" class="field uppercase" maxlength="32" required />
        <p v-if="err('sku')" class="field-error">{{ err('sku') }}</p>
      </div>
      <div>
        <label for="pf-brand" class="field-label">Marca ficticia</label>
        <input id="pf-brand" v-model="form.brand" class="field" maxlength="80" required />
        <p v-if="err('brand')" class="field-error">{{ err('brand') }}</p>
      </div>
      <div>
        <label for="pf-category" class="field-label">Categoría</label>
        <select id="pf-category" v-model.number="form.category_id" class="field">
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <p v-if="err('category_id')" class="field-error">{{ err('category_id') }}</p>
      </div>
      <div>
        <label for="pf-presentation" class="field-label">Presentación</label>
        <input id="pf-presentation" v-model="form.presentation" class="field" maxlength="120" required />
        <p v-if="err('presentation')" class="field-error">{{ err('presentation') }}</p>
      </div>
      <div>
        <label for="pf-price" class="field-label">Precio (S/, IGV incluido)</label>
        <input id="pf-price" v-model.number="form.price" type="number" min="0.01" step="0.01" class="field" required />
        <p v-if="err('price')" class="field-error">{{ err('price') }}</p>
      </div>
      <div>
        <label for="pf-compare" class="field-label">Precio antes (oferta, opcional)</label>
        <input id="pf-compare" v-model.number="form.compare_at_price" type="number" min="0.01" step="0.01" class="field" placeholder="Sin promoción" />
        <p v-if="err('compare_at_price_cents') || err('compare_at_price')" class="field-error">{{ err('compare_at_price_cents') || err('compare_at_price') }}</p>
      </div>
      <div v-if="!isEdit">
        <label for="pf-stock" class="field-label">Stock inicial</label>
        <input id="pf-stock" v-model.number="form.stock" type="number" min="0" step="1" class="field" />
        <p v-if="err('stock')" class="field-error">{{ err('stock') }}</p>
      </div>
      <div>
        <label for="pf-threshold" class="field-label">Alerta de stock bajo</label>
        <input id="pf-threshold" v-model.number="form.low_stock_threshold" type="number" min="0" step="1" class="field" />
      </div>
      <div class="sm:col-span-2">
        <label for="pf-short" class="field-label">Resumen</label>
        <input id="pf-short" v-model="form.short_description" class="field" maxlength="200" required />
        <p v-if="err('short_description')" class="field-error">{{ err('short_description') }}</p>
      </div>
      <div class="sm:col-span-2">
        <label for="pf-description" class="field-label">Descripción</label>
        <textarea id="pf-description" v-model="form.description" rows="4" class="field" maxlength="3000" required />
        <p v-if="err('description')" class="field-error">{{ err('description') }}</p>
      </div>
    </div>

    <fieldset>
      <legend class="field-label">Ilustración</legend>
      <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
        <label v-for="kind in ILLUSTRATIONS" :key="kind" class="cursor-pointer overflow-hidden rounded-xl ring-2 transition" :class="form.illustration === kind ? 'ring-teal-400' : 'ring-transparent'">
          <input v-model="form.illustration" type="radio" name="illustration" :value="kind" class="sr-only" />
          <ProductIllustration :kind="kind" size="sm" :alt="kind" />
        </label>
      </div>
    </fieldset>

    <div>
      <label for="pf-image" class="field-label">Imagen (opcional · JPG, PNG o WebP · máx. 2 MB)</label>
      <label for="pf-image" class="flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-cream-300 bg-white px-4 py-3 text-sm text-ink-500 hover:border-teal-300">
        <ImageUp class="h-4 w-4" aria-hidden="true" /> {{ imageFile?.name ?? 'Seleccionar archivo' }}
      </label>
      <input id="pf-image" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onImage" />
      <p v-if="imageError" class="field-error" role="alert">{{ imageError }}</p>
    </div>

    <div class="flex flex-wrap gap-4 text-sm">
      <label class="flex items-center gap-2"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-sage-600" /> Visible en la tienda</label>
      <label class="flex items-center gap-2"><input v-model="form.is_featured" type="checkbox" class="h-4 w-4 accent-sage-600" /> Destacado</label>
    </div>

    <div class="flex justify-end gap-2 border-t border-cream-200 pt-4">
      <button type="button" class="btn-ghost" @click="emit('cancel')">Cancelar</button>
      <button type="submit" class="btn-primary" :disabled="saving || !!imageError" data-testid="product-save">{{ saving ? 'Guardando…' : isEdit ? 'Guardar cambios' : 'Crear producto' }}</button>
    </div>
  </form>
</template>
