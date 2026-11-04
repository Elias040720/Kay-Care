import { z } from 'zod'
import type { EncounterWrite, HisLineWrite, HisSuggestion, Patient } from '@/types'
import { toPeruISO } from '@/lib/format'

export interface EncounterForm {
  occurred_at: string
  clinical_note: string
  weight_kg: string
  height_cm: string
  hb_g_dl: string
}

const optionalMeasurement = (min: number, max: number, decimals: number, unit: string) =>
  z
    .string()
    .trim()
    .refine(
      (value) => value === '' || /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(value),
      'Ingresa un número decimal válido o deja el campo vacío.',
    )
    .transform((value) => (value === '' ? null : Number(value)))
    .refine(
      (value) => value === null || (Number.isFinite(value) && value >= min && value <= max),
      `Registra un valor entre ${min} y ${max} ${unit}, o deja el campo vacío.`,
    )
    .refine(
      (value) =>
        value === null || (Number.isFinite(value) && Number(value.toFixed(decimals)) === value),
      `Usa como máximo ${decimals === 1 ? '1 decimal' : `${decimals} decimales`}.`,
    )

export const encounterFormSchema = z.object({
  occurred_at: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/, 'Indica la fecha y la hora de atención.')
    .refine((value) => {
      const date = new Date(`${value}:00-05:00`)
      return (
        !Number.isNaN(date.getTime()) &&
        localEncounterDate(date.toISOString()) === value &&
        date.getTime() <= Date.now()
      )
    }, 'La fecha de atención debe ser válida y no puede estar en el futuro.'),
  clinical_note: z.string().trim().max(5000, 'Las notas pueden contener hasta 5 000 caracteres.'),
  weight_kg: optionalMeasurement(0.3, 150, 3, 'kg'),
  height_cm: optionalMeasurement(20, 200, 1, 'cm'),
  hb_g_dl: optionalMeasurement(3, 25, 1, 'g/dL'),
})

export function parseEncounterForm(form: EncounterForm, patient: Patient): EncounterWrite {
  const parsed = encounterFormSchema.parse(form)
  if (parsed.occurred_at.slice(0, 10) < patient.birth_date) {
    throw new z.ZodError([
      {
        code: 'custom',
        path: ['occurred_at'],
        message: 'La atención no puede ser anterior al nacimiento.',
      },
    ])
  }
  return {
    patient_id: patient.id,
    type: 'CRED',
    occurred_at: toPeruISO(parsed.occurred_at),
    clinical_note: parsed.clinical_note || null,
    weight_kg: parsed.weight_kg,
    height_cm: parsed.height_cm,
    hb_g_dl: parsed.hb_g_dl,
  }
}

export interface HisChoice extends HisSuggestion {
  selected: boolean
  diagnosis_type: '' | 'P' | 'D' | 'R'
  lab: string
}

const hisLineSchema = z.object({
  catalog_item_id: z.string().min(1),
  diagnosis_type: z.enum(['P', 'D', 'R']).nullable(),
  lab: z.string().trim().max(10, 'El valor LAB admite hasta 10 caracteres.').nullable(),
})

/** Unchecked suggestions never become submitted activities. */
export function selectedHisLines(choices: HisChoice[]): HisLineWrite[] {
  return choices
    .filter((choice) => choice.selected)
    .map((choice) =>
      hisLineSchema.parse({
        catalog_item_id: choice.catalog_item_id,
        diagnosis_type: choice.diagnosis_type || null,
        lab: choice.lab.trim() || null,
      }),
    )
}

export function localEncounterDate(value: string): string {
  const date = new Date(value)
  const formatter = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'America/Lima',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
  return formatter.format(date).replace(' ', 'T')
}
