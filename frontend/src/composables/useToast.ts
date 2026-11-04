import { ref } from 'vue'
const notices = ref<{ id: number; message: string; kind: 'success' | 'error' | 'info' }[]>([])
let next = 0
export function useToast() {
  function dismiss(id: number) {
    notices.value = notices.value.filter((n) => n.id !== id)
  }
  function notify(message: string, kind: 'success' | 'error' | 'info' = 'success') {
    const id = ++next
    notices.value.push({ id, message, kind })
    window.setTimeout(() => dismiss(id), 8000)
  }
  return { notices, notify, dismiss }
}
