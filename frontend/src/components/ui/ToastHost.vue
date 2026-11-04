<script setup lang="ts">
import { CheckCircle2, AlertCircle, X } from '@lucide/vue'
import { useToast } from '@/composables/useToast'
const { notices, dismiss } = useToast()
</script>
<template>
  <div class="toast-host" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast"
      ><div
        v-for="notice in notices"
        :key="notice.id"
        :class="['toast', notice.kind]"
        :role="notice.kind === 'error' ? 'alert' : 'status'"
      >
        <AlertCircle v-if="notice.kind === 'error'" :size="20" /><CheckCircle2
          v-else
          :size="20"
        /><span>{{ notice.message }}</span
        ><button class="icon-btn" aria-label="Cerrar notificación" @click="dismiss(notice.id)">
          <X :size="16" />
        </button></div
    ></TransitionGroup>
  </div>
</template>
