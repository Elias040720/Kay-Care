<script setup lang="ts">
import { onMounted } from 'vue'
import { Clock, Mail, MapPin, Phone } from 'lucide-vue-next'
import BrandMark from './BrandMark.vue'
import { DEMO_NOTICE } from '@/utils/format'
import { useCatalogStore } from '@/stores/catalog'

const catalog = useCatalogStore()
onMounted(() => catalog.loadCategories())
const year = new Date().getFullYear()
</script>

<template>
  <footer class="mt-16 bg-ink-900 text-cream-100">
    <div class="bg-clinic-700">
      <p class="container-page py-3 text-center text-xs font-bold uppercase tracking-[0.14em] text-white sm:text-sm" role="note">
        {{ DEMO_NOTICE }}
      </p>
    </div>

    <div class="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <BrandMark tone="light" />
        <p class="mt-4 max-w-xs text-sm text-cream-200">
          Farmacia online demostrativa desarrollada como proyecto universitario de Cloud Computing.
          Productos y marcas ficticios de venta libre.
        </p>
      </div>

      <div>
        <h2 class="mb-3 font-sans text-xs font-bold uppercase tracking-[0.18em] text-white">Categorías</h2>
        <ul class="space-y-2 text-sm text-cream-200">
          <li v-for="category in catalog.categories" :key="category.id">
            <RouterLink :to="{ name: 'catalog', query: { categoria: category.slug } }" class="hover:text-white hover:underline">
              {{ category.name }}
            </RouterLink>
          </li>
          <li><RouterLink :to="{ name: 'catalog', query: { ofertas: '1' } }" class="hover:text-white hover:underline">Ofertas</RouterLink></li>
        </ul>
      </div>

      <div>
        <h2 class="mb-3 font-sans text-xs font-bold uppercase tracking-[0.18em] text-white">El proyecto</h2>
        <ul class="space-y-2 text-sm text-cream-200">
          <li>Vue 3 · Laravel 12 · PostgreSQL</li>
          <li>Docker · Terraform · GitHub Actions</li>
          <li><RouterLink to="/catalogo" class="hover:text-white hover:underline">Catálogo completo</RouterLink></li>
          <li><RouterLink to="/admin/login" class="hover:text-white hover:underline">Administración (demo)</RouterLink></li>
        </ul>
        <p class="mt-4 text-xs text-cream-200">
          Módulo independiente del sistema clínico Kay Care Enfermería: no comparte datos, sesiones ni base de datos.
        </p>
      </div>

      <div>
        <h2 class="mb-3 font-sans text-xs font-bold uppercase tracking-[0.18em] text-white">Contacto demostrativo</h2>
        <ul class="space-y-2.5 text-sm text-cream-200">
          <li class="flex items-center gap-2"><Mail class="h-4 w-4 shrink-0" aria-hidden="true" /> contacto@farmacia.demo</li>
          <li class="flex items-center gap-2"><Phone class="h-4 w-4 shrink-0" aria-hidden="true" /> (01) 000-0000</li>
          <li class="flex items-center gap-2"><MapPin class="h-4 w-4 shrink-0" aria-hidden="true" /> Av. Ficticia 123, Lima</li>
          <li class="flex items-center gap-2"><Clock class="h-4 w-4 shrink-0" aria-hidden="true" /> Lun. a dom. · 8:00 – 22:00</li>
        </ul>
        <p class="mt-3 text-xs text-cream-200">Datos de contacto ficticios, solo para la demostración.</p>
      </div>
    </div>

    <div class="border-t border-white/10">
      <div class="container-page flex flex-col gap-2 py-5 text-xs text-cream-200 sm:flex-row sm:items-center sm:justify-between">
        <p>© {{ year }} Kay Care Farmacia — tienda demostrativa sin fines comerciales.</p>
        <p>Ningún contenido constituye consejo médico. Ante cualquier síntoma, consulta a un profesional de la salud.</p>
      </div>
    </div>
  </footer>
</template>
