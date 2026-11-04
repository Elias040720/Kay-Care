<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
import PageHeader from '@/components/ui/PageHeader.vue'
</script>
<template>
  <div>
    <PageHeader
      eyebrow="ESTAMOS PARA ACOMPAÑARTE"
      title="No encontramos esta página"
      description="Puede que el enlace haya cambiado. Vuelve a tu espacio de trabajo para continuar."
    /><RouterLink to="/" class="btn btn-primary"
      ><ArrowLeft :size="18" />Volver al inicio</RouterLink
    >
  </div>
</template>
