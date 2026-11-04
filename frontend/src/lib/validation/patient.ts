import { z } from 'zod'
import { todayInPeru } from '@/lib/format'
const nullableText = (max: number) =>
  z
    .string()
    .trim()
    .max(max, `Usa hasta ${max} caracteres.`)
    .transform((value) => value || null)
    .nullable()
export const patientSchema = z
  .object({
    chart_number: z
      .string()
      .trim()
      .min(1, 'Ingresa el número de historia clínica.')
      .max(50, 'Usa hasta 50 caracteres.'),
    first_name: z
      .string()
      .trim()
      .min(1, 'Ingresa los nombres.')
      .max(100, 'Usa hasta 100 caracteres.'),
    last_name: z
      .string()
      .trim()
      .min(1, 'Ingresa los apellidos.')
      .max(100, 'Usa hasta 100 caracteres.'),
    birth_date: z
      .string()
      .regex(/^\d{4}-\d{2}-\d{2}$/, 'Ingresa una fecha válida.')
      .refine((value) => {
        const date = new Date(`${value}T12:00:00Z`)
        return (
          !Number.isNaN(date.getTime()) &&
          date.toISOString().slice(0, 10) === value &&
          value <= todayInPeru()
        )
      }, 'La fecha debe ser válida y no puede estar en el futuro.'),
    sex: z.enum(['F', 'M', 'X'], { error: 'Selecciona una opción.' }),
    document_type: nullableText(30),
    document_number: nullableText(30),
    locality: nullableText(160),
  })
  .superRefine((value, ctx) => {
    if (value.document_number && !value.document_type)
      ctx.addIssue({
        code: 'custom',
        path: ['document_type'],
        message: 'Indica el tipo de documento.',
      })
    if (
      value.document_number &&
      value.document_type === 'DNI' &&
      !/^\d{8}$/.test(value.document_number)
    )
      ctx.addIssue({
        code: 'custom',
        path: ['document_number'],
        message: 'El DNI debe tener 8 dígitos.',
      })
  })
