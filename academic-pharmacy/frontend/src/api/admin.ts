import { ensureCsrfCookie, http } from './client'
import type { AdminProduct, AdminUser, DashboardSummary, Order, OrderStatus, Paginated, StockMovement } from '@/types'

export interface AdminProductQuery {
  q?: string
  category?: string
  active?: boolean | null
  low_stock?: boolean
  sort?: string
  page?: number
  per_page?: number
}

export interface ProductPayload {
  category_id: number
  sku: string
  name: string
  short_description: string
  description: string
  presentation: string
  brand: string
  price: number
  /** Precio «antes» (oferta ficticia). null o vacío = sin promoción. */
  compare_at_price: number | null
  stock?: number
  low_stock_threshold: number
  illustration: string
  is_active: boolean
  is_featured: boolean
}

export const adminApi = {
  async login(email: string, password: string) {
    await ensureCsrfCookie(true)
    return http.post<{ data: AdminUser }>('/admin/login', { email, password }).then((r) => r.data)
  },
  me: () => http.get<{ data: AdminUser }>('/admin/me').then((r) => r.data),
  logout: () => http.post<{ message: string }>('/admin/logout'),

  dashboard: () => http.get<{ data: DashboardSummary }>('/admin/dashboard').then((r) => r.data),

  products: (query: AdminProductQuery) => http.get<Paginated<AdminProduct>>('/admin/products', { query: { ...query, active: query.active === null || query.active === undefined ? undefined : query.active ? '1' : '0' } }),
  product: (id: number) => http.get<{ data: AdminProduct }>(`/admin/products/${id}`).then((r) => r.data),
  createProduct: (payload: ProductPayload) => http.post<{ data: AdminProduct }>('/admin/products', payload).then((r) => r.data),
  updateProduct: (id: number, payload: Partial<ProductPayload>) => http.put<{ data: AdminProduct }>(`/admin/products/${id}`, payload).then((r) => r.data),
  deleteProduct: (id: number) => http.delete<{ message: string }>(`/admin/products/${id}`),
  uploadImage: (id: number, file: File) => {
    const form = new FormData()
    form.append('image', file)
    return http.post<{ data: AdminProduct }>(`/admin/products/${id}/image`, form).then((r) => r.data)
  },
  adjustStock: (id: number, delta: number, reason: 'adjustment' | 'restock', note?: string) =>
    http.post<{ data: AdminProduct }>(`/admin/products/${id}/stock`, { delta, reason, note: note || null }).then((r) => r.data),
  movements: (query: { product_id?: number; per_page?: number } = {}) =>
    http.get<{ data: StockMovement[]; meta: { current_page: number; last_page: number; total: number } }>('/admin/stock-movements', { query }),

  orders: (query: { status?: OrderStatus | ''; q?: string; page?: number; per_page?: number }) =>
    http.get<Paginated<Order>>('/admin/orders', { query }),
  order: (id: number) => http.get<{ data: Order }>(`/admin/orders/${id}`).then((r) => r.data),
  updateOrderStatus: (id: number, status: OrderStatus, note?: string) =>
    http.patch<{ data: Order }>(`/admin/orders/${id}/status`, { status, note: note || null }).then((r) => r.data),
}
