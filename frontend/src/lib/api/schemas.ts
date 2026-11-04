import { z } from 'zod'

const id = z.string().uuid()
const nullableText = z.string().nullable()
const diagnosisType = z.enum(['P', 'D', 'R']).nullable()
const verificationStatus = z.enum(['draft', 'verified'])
const calendarDate = z
  .string()
  .regex(/^\d{4}-\d{2}-\d{2}$/)
  .refine((value) => {
    const parsed = new Date(`${value}T00:00:00.000Z`)
    return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value
  }, 'Ingresa una fecha válida.')

export const userSchema = z.object({
  id,
  name: z.string(),
  email: z.string().email(),
  role: z.enum(['nurse', 'admin']),
})

export const patientWriteSchema = z.object({
  chart_number: z.string().trim().min(1).max(100),
  first_name: z.string().trim().min(1).max(150),
  last_name: z.string().trim().min(1).max(150),
  birth_date: calendarDate,
  sex: z.enum(['F', 'M', 'X']),
  document_type: nullableText,
  document_number: nullableText,
  locality: nullableText,
})
export const patientSchema = patientWriteSchema.extend({ id })

export const encounterWriteSchema = z.object({
  patient_id: id,
  type: z.literal('CRED'),
  occurred_at: z.string().datetime({ offset: true }),
  clinical_note: nullableText,
  weight_kg: z.number().finite().positive().nullable(),
  height_cm: z.number().finite().positive().nullable(),
  hb_g_dl: z.number().finite().nonnegative().nullable(),
})
export const encounterSchema = encounterWriteSchema.extend({
  id,
  status: z.enum(['draft', 'reviewed']),
})

export const hisSuggestionSchema = z.object({
  catalog_item_id: id,
  label: z.string(),
  code: z.string(),
  diagnosis_type_suggested: diagnosisType,
  lab_suggested: nullableText,
  verification_status: verificationStatus,
  source: z.string(),
})
export const hisLineWriteSchema = z.object({
  catalog_item_id: id,
  diagnosis_type: diagnosisType,
  lab: nullableText,
})
export const hisLineSchema = hisLineWriteSchema.extend({
  id,
  label: z.string(),
  code: z.string(),
  verification_status: verificationStatus,
})
export const hisPreviewSchema = z.object({
  patient: patientSchema,
  encounter: encounterSchema,
  lines: z.array(hisLineSchema),
  warnings: z.array(z.string()),
  validated: z.boolean(),
})
export const paginatedPatientsSchema = z.object({
  data: z.array(patientSchema),
  meta: z.object({
    current_page: z.number().int().positive(),
    last_page: z.number().int().positive(),
    per_page: z.number().int().positive(),
    total: z.number().int().nonnegative(),
  }),
})
export const healthSchema = z.object({ status: z.literal('ok'), version: z.literal('v1') })
export const envelope = <T extends z.ZodTypeAny>(schema: T) => z.object({ data: schema })
