import { onBeforeUnmount, onMounted, type Ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { useAuthStore } from '@/features/auth/store'
const activeForms = new Set<Ref<boolean>>()
export function confirmAllUnsaved() {
  return (
    ![...activeForms].some((form) => form.value) ||
    window.confirm('Tienes cambios sin guardar. ¿Quieres descartarlos y salir?')
  )
}
export function useUnsavedChanges(dirty: Ref<boolean>) {
  const auth = useAuthStore()
  function confirmDiscard() {
    return (
      !dirty.value || window.confirm('Tienes cambios sin guardar. ¿Quieres descartarlos y salir?')
    )
  }
  onBeforeRouteLeave(() => !auth.user || confirmDiscard())
  onBeforeRouteUpdate(() => !auth.user || confirmDiscard())
  function beforeUnload(event: BeforeUnloadEvent) {
    if (dirty.value) {
      event.preventDefault()
      event.returnValue = ''
    }
  }
  onMounted(() => {
    activeForms.add(dirty)
    window.addEventListener('beforeunload', beforeUnload)
  })
  onBeforeUnmount(() => {
    activeForms.delete(dirty)
    window.removeEventListener('beforeunload', beforeUnload)
  })
  return { confirmDiscard }
}
