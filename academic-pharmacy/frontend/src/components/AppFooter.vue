<script setup lang="ts">
import BrandMark from './BrandMark.vue'
import { DEMO_NOTICE } from '@/utils/format'
</script>

<template>
  <footer class="mt-20 border-t border-cream-200 bg-cream-50">
    <div class="container-page grid gap-8 py-10 md:grid-cols-3">
      <div>
        <BrandMark />
        <p class="mt-3 max-w-xs text-sm text-ink-500">
          Tienda farmacéutica demostrativa construida con fines académicos. Módulo independiente del sistema clínico Kay Care Enfermería.
        </p>
      </div>
      <div class="text-sm text-ink-500">
        <h2 class="mb-2 font-sans text-xs font-semibold uppercase tracking-[0.18em] text-ink-700">Explorar</h2>
        <ul class="space-y-1.5">
          <li><RouterLink to="/catalogo" class="hover:text-sage-700">Catálogo</RouterLink></li>
          <li><RouterLink to="/checkout" class="hover:text-sage-700">Checkout simulado</RouterLink></li>
          <li><RouterLink to="/admin/login" class="hover:text-sage-700">Administración</RouterLink></li>
        </ul>
      </div>
      <div class="text-sm text-ink-500">
        <h2 class="mb-2 font-sans text-xs font-semibold uppercase tracking-[0.18em] text-ink-700">Aviso</h2>
        <p class="font-semibold text-ink-700">{{ DEMO_NOTICE }}</p>
        <p class="mt-2">Ningún contenido constituye consejo médico. Ante cualquier síntoma, consulta a un profesional de la salud.</p>
      </div>
    </div>
  </footer>
</template>
