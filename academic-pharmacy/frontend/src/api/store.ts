import { http } from './client'
import type { CartLineInput, CartValidation, Category, CheckoutResponse, Order, Paginated, Product } from '@/types'

export interface ProductQuery {
  q?: string
  category?: string
  min_price?: number | null
  max_price?: number | null
  in_stock?: boolean
  featured?: boolean
  on_sale?: boolean
  sort?: string
  page?: number
  per_page?: number
}

export const storeApi = {
  categories: () => http.get<{ data: Category[] }>('/categories').then((r) => r.data),

  products: (query: ProductQuery, signal?: AbortSignal) =>
    http.get<Paginated<Product>>('/products', { query: { ...query }, signal }),

  product: (slug: string) => http.get<{ data: Product }>(`/products/${encodeURIComponent(slug)}`).then((r) => r.data),

  related: (slug: string) => http.get<{ data: Product[] }>(`/products/${encodeURIComponent(slug)}/related`).then((r) => r.data),

  validateCart: (items: CartLineInput[]) => http.post<{ data: CartValidation }>('/cart/validate', { items }).then((r) => r.data),

  checkout: (payload: { buyer_alias: string; accept_demo_terms: boolean; items: CartLineInput[] }, idempotencyKey: string) =>
    http.post<CheckoutResponse>('/checkout', payload, { headers: { 'Idempotency-Key': idempotencyKey } }),

  order: (number: string, token: string) =>
    http.get<{ data: Order }>(`/orders/${encodeURIComponent(number)}`, { query: { token } }).then((r) => r.data),
}
