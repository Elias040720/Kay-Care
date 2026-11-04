import type { ApiAdapter } from '@/types'
import { MockApiAdapter } from '@/lib/mock/adapter'
import { RealApiAdapter } from './real'

const configuredMode: string = import.meta.env.VITE_API_MODE ?? 'mock'
if (configuredMode !== 'mock' && configuredMode !== 'real') {
  throw new Error('VITE_API_MODE debe ser mock o real.')
}

export const apiMode: 'mock' | 'real' = configuredMode
export const isDemo = apiMode === 'mock'
export const api: ApiAdapter = isDemo
  ? new MockApiAdapter()
  : new RealApiAdapter(import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000')

export { AppError, normalizeError } from './errors'
export { RealApiAdapter } from './real'
export { MockApiAdapter } from '@/lib/mock/adapter'
