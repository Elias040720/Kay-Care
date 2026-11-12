import type { Component } from 'vue'
import { Baby, BriefcaseMedical, Droplets, HeartPulse, Package, Pill, Sparkles } from 'lucide-vue-next'

export interface CategoryMeta {
  icon: Component
  /** Clases de fondo/texto para el círculo del icono (contraste AA). */
  tone: string
  tagline: string
}

// Iconos y tonos por slug. Las categorías vienen de la API; un slug nuevo
// creado desde la base de datos cae en el valor por defecto.
const META: Record<string, CategoryMeta> = {
  'medicamentos-venta-libre': { icon: Pill, tone: 'bg-clinic-50 text-clinic-700', tagline: 'Venta libre de uso común' },
  'vitaminas-y-bienestar': { icon: HeartPulse, tone: 'bg-honey-100 text-honey-600', tagline: 'Vitaminas y complementos' },
  'cuidado-personal': { icon: Sparkles, tone: 'bg-teal-50 text-teal-700', tagline: 'Piel, sol e hidratación' },
  higiene: { icon: Droplets, tone: 'bg-mist-50 text-mist-600', tagline: 'Oral, manos y hogar' },
  'primeros-auxilios': { icon: BriefcaseMedical, tone: 'bg-promo-50 text-promo-700', tagline: 'Botiquín del hogar' },
  'cuidado-infantil': { icon: Baby, tone: 'bg-sage-50 text-sage-700', tagline: 'Bebés y niños' },
}

const FALLBACK: CategoryMeta = { icon: Package, tone: 'bg-sage-50 text-sage-700', tagline: 'Productos ficticios' }

export function categoryMeta(slug: string | undefined | null): CategoryMeta {
  return (slug && META[slug]) || FALLBACK
}
