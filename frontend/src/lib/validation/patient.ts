import { z } from 'zod'
import { todayInPeru } from '@/lib/format'
const nullableText = (max: number) =>
  z
    .string()
    .trim()
    .transform((value) => value.replace(/\s+/gu, ' '))
    .pipe(z.string().max(max, `Usa hasta ${max} caracteres.`))
    .transform((value) => value || null)
    .nullable()
const nameSchema = (max: number, requiredMessage: string) =>
  z
    .string()
    .trim()
    .transform((value) => value.replace(/\s+/gu, ' '))
    .pipe(
      z
        .string()
        .min(1, requiredMessage)
        .max(max, `Usa hasta ${max} caracteres.`)
        .regex(/^[\p{L}\p{M}' .\-]+$/u, 'Usa letras, espacios, apóstrofes, puntos o guiones.'),
    )
const documentTypes = ['DNI', 'CE', 'PASAPORTE', 'CNV', 'OTRO'] as const
const documentTypeSchema = z.preprocess(
  (value) => (typeof value === 'string' ? value.trim().toUpperCase() || null : value),
  z.enum(documentTypes, { error: 'Selecciona un tipo de documento válido.' }).nullable(),
)
const documentNumberSchema = z.preprocess(
  (value) =>
    typeof value === 'string' ? value.replace(/[\s\-]+/gu, '').toUpperCase() || null : value,
  z
    .string()
    .min(4, 'El documento debe tener al menos 4 caracteres.')
    .max(20, 'Usa hasta 20 caracteres.')
    .regex(/^[A-Z0-9]+$/, 'El documento solo admite letras y números.')
    .nullable(),
)
export const patientSchema = z
  .object({
    chart_number: z
      .string()
      .transform((value) => value.replace(/\s+/gu, '').toUpperCase())
      .pipe(
        z
          .string()
          .min(1, 'Ingresa el número de historia clínica.')
          .max(30, 'Usa hasta 30 caracteres.')
          .regex(
            /^[A-Z0-9\-/]+$/,
            'La historia clínica solo admite letras, números, guiones y barras.',
          ),
      ),
    first_name: nameSchema(100, 'Ingresa los nombres.'),
    last_name: nameSchema(150, 'Ingresa los apellidos.'),
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
      }, 'La fecha debe ser válida y no puede estar en el futuro.')
      .refine(
        (value) => value >= '1900-01-01',
        'La fecha de nacimiento debe ser desde el 1 de enero de 1900.',
      ),
    sex: z.enum(['F', 'M', 'X'], { error: 'Selecciona una opción.' }),
    document_type: documentTypeSchema,
    document_number: documentNumberSchema,
    locality: nullableText(150),
  })
  .superRefine((value, ctx) => {
    if (value.document_number && !value.document_type)
      ctx.addIssue({
        code: 'custom',
        path: ['document_type'],
        message: 'Indica el tipo de documento.',
      })
    if (value.document_type && !value.document_number)
      ctx.addIssue({
        code: 'custom',
        path: ['document_number'],
        message: 'Indica el número de documento o selecciona «Sin documento».',
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
