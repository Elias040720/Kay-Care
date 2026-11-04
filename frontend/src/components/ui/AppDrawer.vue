<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { X } from '@lucide/vue'
const props = defineProps<{ open: boolean; title: string }>()
const emit = defineEmits<{ close: [] }>()
const dialog = ref<HTMLDialogElement>()
let previous: HTMLElement | null = null
watch(
  () => props.open,
  async (open) => {
    await nextTick()
    if (open) {
      previous = document.activeElement as HTMLElement
      dialog.value?.showModal()
      document.body.style.overflow = 'hidden'
    } else {
      dialog.value?.close()
      document.body.style.overflow = ''
      if (
        previous?.isConnected &&
        previous.matches('button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])')
      )
        previous.focus()
      else document.querySelector<HTMLElement>('[data-drawer-trigger]')?.focus()
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>
<template>
  <dialog
    ref="dialog"
    class="drawer"
    aria-labelledby="drawer-title"
    @cancel.prevent="emit('close')"
    @click="
      (e) => {
        if (e.target === dialog) emit('close')
      }
    "
  >
    <div class="drawer-body">
      <header class="drawer-heading">
        <div>
          <p class="eyebrow">KAY CARE · PACIENTES</p>
          <h2 id="drawer-title">{{ title }}</h2>
        </div>
        <button class="icon-btn" aria-label="Cerrar panel" @click="emit('close')">
          <X :size="21" />
        </button>
      </header>
      <slot />
    </div>
  </dialog>
</template>
