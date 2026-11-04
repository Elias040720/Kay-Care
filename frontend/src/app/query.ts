import { QueryClient } from '@tanstack/vue-query'
import { AppError } from '@/lib/api/errors'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      retry: (count, error) =>
        !(error instanceof AppError && [401, 419, 422, 404].includes(error.status)) && count < 1,
    },
    mutations: { retry: false },
  },
})
