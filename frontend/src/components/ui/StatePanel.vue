<script setup lang="ts">
import { AlertCircle, Inbox, RotateCcw } from '@lucide/vue'
withDefaults(
  defineProps<{
    loading?: boolean
    error?: unknown
    empty?: boolean
    title?: string
    description?: string
  }>(),
  {
    title: 'Aún no hay registros',
    description: 'Los registros aparecerán aquí cuando los guardes.',
  },
)
defineEmits<{ retry: [] }>()
</script>
<template>
  <div v-if="loading" class="loading-state" role="status" aria-label="Cargando información">
    <div v-for="n in 3" :key="n" class="skeleton" />
    <span class="sr-only">Cargando información…</span>
  </div>
  <div v-else-if="error" class="state-panel error-panel" role="alert">
    <AlertCircle :size="28" />
    <h3>No pudimos cargar la información</h3>
    <p>
      {{ error instanceof Error ? error.message : 'Comprueba tu conexión e inténtalo nuevamente.' }}
    </p>
    <button class="btn btn-secondary" @click="$emit('retry')">
      <RotateCcw :size="16" />Volver a intentar
    </button>
  </div>
  <div v-else-if="empty" class="state-panel">
    <Inbox :size="32" />
    <h3>{{ title }}</h3>
    <p>{{ description }}</p>
    <slot name="action" />
  </div>
  <slot v-else />
</template>
