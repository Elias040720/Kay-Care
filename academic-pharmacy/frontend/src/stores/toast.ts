import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastTone = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  tone: ToastTone
  title: string
  message?: string
}

let seq = 0

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  const timers = new Map<number, ReturnType<typeof setTimeout>>()

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
    const timer = timers.get(id)
    if (timer) clearTimeout(timer)
    timers.delete(id)
  }

  function push(tone: ToastTone, title: string, message?: string, timeout = 4200) {
    const id = ++seq
    toasts.value = [...toasts.value.slice(-3), { id, tone, title, message }]
    if (timeout > 0) timers.set(id, setTimeout(() => dismiss(id), timeout))
    return id
  }

  return {
    toasts,
    dismiss,
    success: (title: string, message?: string) => push('success', title, message),
    error: (title: string, message?: string) => push('error', title, message, 6500),
    info: (title: string, message?: string) => push('info', title, message),
  }
})
