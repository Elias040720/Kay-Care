<script setup lang="ts">
import { Compass } from 'lucide-vue-next'
import EmptyState from '@/components/EmptyState.vue'
</script>

<template>
  <div class="container-page py-16">
    <EmptyState :icon="Compass" title="Página no encontrada" description="La dirección no existe en la tienda demostrativa.">
      <RouterLink to="/" class="btn-primary">Ir al inicio</RouterLink>
      <RouterLink to="/catalogo" class="btn-ghost">Ver catálogo</RouterLink>
    </EmptyState>
  </div>
</template>
