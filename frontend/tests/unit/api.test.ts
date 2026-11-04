import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  AxiosError,
  AxiosHeaders,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios'
import type { EncounterWrite, PatientWrite } from '@/types'
import { AppError, normalizeError } from '@/lib/api/errors'
import { createHttpClient, RealApiAdapter } from '@/lib/api/real'
import { MockApiAdapter } from '@/lib/mock/adapter'
import { createFixtures, DEMO_USER } from '@/lib/mock/fixtures'

const patientInput: PatientWrite = {
  chart_number: 'TEST-001',
  first_name: 'Paciente',
  last_name: 'Prueba unitaria',
  birth_date: '2025-01-01',
  sex: 'X',
  document_type: null,
  document_number: null,
  locality: null,
}
const encounterInput = (patientId: string): EncounterWrite => ({
  patient_id: patientId,
  type: 'CRED',
  occurred_at: '2026-10-20T09:30:00-05:00',
  clinical_note: null,
  weight_kg: null,
  height_cm: null,
  hb_g_dl: null,
})

function response(config: InternalAxiosRequestConfig, data: unknown, status = 200): AxiosResponse {
  return { data, status, statusText: String(status), headers: new AxiosHeaders(), config }
}

describe('MockApiAdapter: flujo completo sin servidor', () => {
  let api: MockApiAdapter
  beforeEach(async () => {
    api = new MockApiAdapter(0)
    await api.login({ email: 'demo@kaycare.example', password: '' })
  })

  it('no inicia una sesión por sí solo y logout descarta datos de sesión', async () => {
    const fresh = new MockApiAdapter(0)
    await expect(fresh.me()).rejects.toMatchObject({ status: 401 })
    await api.createPatient(patientInput)
    await api.logout()
    await expect(api.listPatients()).rejects.toMatchObject({ status: 401 })
    await api.login({ email: '', password: '' })
    expect((await api.listPatients('TEST-001')).meta.total).toBe(0)
  })

  it('pagina y busca por nombre, documento o HC sin distinguir tildes', async () => {
    const first = await api.listPatients()
    const second = await api.listPatients('', 2)
    expect(first.meta.total).toBe(14)
    expect(first.data).toHaveLength(8)
    expect(second.data).toHaveLength(6)
    expect((await api.listPatients('lucia')).data[0]?.first_name).toBe('Lucía')
    expect((await api.listPatients('DEMO-0001')).meta.total).toBe(1)
    await api.createPatient({
      ...patientInput,
      document_type: 'DEMO',
      document_number: 'DOCUMENTO-FICTICIO',
    })
    expect((await api.listPatients('DOCUMENTO-FICTICIO')).meta.total).toBe(1)
    expect((await api.listPatients('inexistente')).data).toEqual([])
  })

  it('crea, modifica y retorna copias sin permitir mutaciones externas', async () => {
    const patient = await api.createPatient(patientInput)
    expect(patient.id).toMatch(/^[\da-f-]{36}$/)
    await api.updatePatient(patient.id, { locality: 'Sector de prueba' })
    patient.first_name = 'Mutación externa'
    const stored = await api.getPatient(patient.id)
    expect(stored.first_name).toBe('Paciente')
    expect(stored.locality).toBe('Sector de prueba')
    await expect(api.createPatient(patientInput)).rejects.toMatchObject({
      status: 422,
      fields: { chart_number: expect.any(Array) },
    })
  })

  it('rechaza fechas imposibles, futuras y atenciones anteriores al nacimiento', async () => {
    await expect(
      api.createPatient({ ...patientInput, birth_date: '2025-02-30' }),
    ).rejects.toMatchObject({ status: 422 })
    await expect(
      api.createPatient({ ...patientInput, birth_date: '2099-01-01' }),
    ).rejects.toMatchObject({ status: 422 })
    const patient = await api.createPatient(patientInput)
    await expect(
      api.createEncounter({
        ...encounterInput(patient.id),
        occurred_at: '2024-01-01T12:00:00-05:00',
      }),
    ).rejects.toMatchObject({ status: 422 })
    await expect(
      api.createEncounter({ ...encounterInput(patient.id), occurred_at: '2026-10-20T09:30:00' }),
    ).rejects.toMatchObject({ status: 422 })
  })

  it('guarda atención y únicamente las actividades confirmadas, sin completar LAB', async () => {
    const patient = await api.createPatient(patientInput)
    const encounter = await api.createEncounter(encounterInput(patient.id))
    expect((await api.patientEncounters(patient.id)).map((entry) => entry.id)).toContain(
      encounter.id,
    )
    await api.updateEncounter(encounter.id, { weight_kg: 8.2 })
    expect((await api.getEncounter(encounter.id)).weight_kg).toBe(8.2)
    const suggestions = await api.hisSuggestions(encounter.id)
    expect(suggestions.every((entry) => entry.code.startsWith('DEMO-'))).toBe(true)
    expect(
      suggestions.every(
        (entry) => entry.lab_suggested === null && entry.verification_status === 'draft',
      ),
    ).toBe(true)
    expect((await api.hisPreview(encounter.id)).lines).toEqual([])
    const selected = suggestions[0]!
    await api.saveHisLines(encounter.id, [
      { catalog_item_id: selected.catalog_item_id, diagnosis_type: 'D', lab: null },
    ])
    const preview = await api.hisPreview(encounter.id)
    expect(preview.lines).toHaveLength(1)
    expect(preview.lines[0]?.lab).toBeNull()
    expect(preview.validated).toBe(false)
    expect(preview.warnings.join(' ')).toContain('DEMO')
    await api.saveHisLines(encounter.id, [])
    expect((await api.hisPreview(encounter.id)).lines).toEqual([])
  })

  it('rechaza actividades duplicadas o desconocidas sin guardar parcialmente', async () => {
    const patient = await api.createPatient(patientInput)
    const encounter = await api.createEncounter(encounterInput(patient.id))
    const suggestions = await api.hisSuggestions(encounter.id)
    const line = {
      catalog_item_id: suggestions[0]!.catalog_item_id,
      diagnosis_type: null,
      lab: null,
    }
    await expect(api.saveHisLines(encounter.id, [line, line])).rejects.toMatchObject({
      status: 422,
    })
    await expect(
      api.saveHisLines(encounter.id, [
        { ...line, catalog_item_id: '99999999-0000-4000-8000-000000000001' },
      ]),
    ).rejects.toMatchObject({ status: 422 })
    expect((await api.hisPreview(encounter.id)).lines).toHaveLength(0)
  })

  it('produce un PDF claramente DEMO con estructura y offsets válidos', async () => {
    const patient = await api.createPatient(patientInput)
    const encounter = await api.createEncounter(encounterInput(patient.id))
    const blob = await api.downloadDocument(encounter.id, 'clinical-note')
    expect(blob.type).toBe('application/pdf')
    const pdf = await blob.text()
    expect(pdf.startsWith('%PDF-1.4')).toBe(true)
    expect(pdf).toContain('DEMO - SIN VALIDEZ OFICIAL')
    expect(pdf.endsWith('%%EOF')).toBe(true)
    const xrefOffset = Number(pdf.match(/startxref\n(\d+)/)?.[1])
    expect(pdf.slice(xrefOffset, xrefOffset + 4)).toBe('xref')
    expect(pdf).toContain('No acredita atencion')
  })

  it('no inventa registros para un UUID inexistente', async () => {
    await expect(api.getPatient('99999999-0000-4000-8000-000000000001')).rejects.toMatchObject({
      status: 404,
    })
    await expect(api.hisPreview('99999999-0000-4000-8000-000000000001')).rejects.toMatchObject({
      status: 404,
    })
  })
})

describe('RealApiAdapter: contrato, sesión y errores', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('configura cookies/XSRF y pide CSRF antes de login sin almacenar tokens', async () => {
    const http = createHttpClient('http://localhost:8000/')
    const paths: string[] = []
    const acceptHeaders: unknown[] = []
    http.defaults.adapter = async (config) => {
      paths.push(config.url!)
      acceptHeaders.push(config.headers.get('Accept'))
      return response(config, config.url === '/sanctum/csrf-cookie' ? '' : { data: DEMO_USER })
    }
    const api = new RealApiAdapter('http://localhost:8000', http)
    expect(await api.login({ email: 'demo@kaycare.example', password: 'test-only' })).toEqual(
      DEMO_USER,
    )
    expect(paths).toEqual(['/sanctum/csrf-cookie', '/api/v1/auth/login'])
    expect(http.defaults.withCredentials).toBe(true)
    expect(http.defaults.withXSRFToken).toBe(true)
    expect(acceptHeaders).toEqual(['application/json', 'application/json'])
  })

  it('envía búsqueda y paginación en el endpoint permitido', async () => {
    const http = createHttpClient('http://localhost:8000')
    let request: InternalAxiosRequestConfig | undefined
    http.defaults.adapter = async (config) => {
      request = config
      return response(config, {
        data: [],
        meta: { current_page: 2, last_page: 2, per_page: 8, total: 8 },
      })
    }
    const api = new RealApiAdapter(undefined, http)
    await api.listPatients('nombre ficticio', 2)
    expect(request?.url).toBe('/api/v1/patients')
    expect(request?.params).toEqual({ search: 'nombre ficticio', page: 2 })
  })

  it('preserva el contrato de creación CRED y reemplazo explícito de líneas HIS', async () => {
    const http = createHttpClient('http://localhost:8000')
    const savedEncounter = createFixtures().encounters[0]!
    const requests: InternalAxiosRequestConfig[] = []
    http.defaults.adapter = async (config) => {
      requests.push(config)
      return response(config, { data: config.url?.endsWith('/his-lines') ? [] : savedEncounter })
    }
    const api = new RealApiAdapter(undefined, http)
    const input = encounterInput(savedEncounter.patient_id)
    await api.createEncounter(input)
    await api.updateEncounter(savedEncounter.id, {
      clinical_note: 'Solo texto ficticio de prueba.',
    })
    await api.saveHisLines(savedEncounter.id, [])
    expect(requests.map((entry) => [entry.method, entry.url])).toEqual([
      ['post', '/api/v1/encounters'],
      ['patch', `/api/v1/encounters/${savedEncounter.id}`],
      ['put', `/api/v1/encounters/${savedEncounter.id}/his-lines`],
    ])
    expect(JSON.parse(requests[0]!.data as string)).toEqual(input)
    expect(JSON.parse(requests[1]!.data as string)).toEqual({
      clinical_note: 'Solo texto ficticio de prueba.',
    })
    expect(JSON.parse(requests[2]!.data as string)).toEqual({ lines: [] })
  })

  it('valida respuestas JSON y nunca reemplaza errores con datos demo', async () => {
    const http = createHttpClient('http://localhost:8000')
    http.defaults.adapter = async (config) => response(config, { data: { id: 'invalid-id' } })
    const api = new RealApiAdapter(undefined, http)
    await expect(api.getPatient('10000000-0000-4000-8000-000000000001')).rejects.toMatchObject({
      status: 502,
    })
    http.defaults.adapter = async (config) => {
      throw new AxiosError('Network unavailable', 'ERR_NETWORK', config)
    }
    await expect(api.listPatients()).rejects.toMatchObject({ status: 0 })
  })

  it.each([401, 419])(
    'notifica sesión expirada en endpoints protegidos (%i), pero no durante me/login',
    async (status) => {
      const dispatchEvent = vi.fn()
      vi.stubGlobal('window', { dispatchEvent })
      const http = createHttpClient('http://localhost:8000')
      http.defaults.adapter = async (config) => {
        throw new AxiosError(
          'Unauthorized',
          'ERR_BAD_REQUEST',
          config,
          undefined,
          response(config, {}, status),
        )
      }
      const api = new RealApiAdapter(undefined, http)
      await expect(api.me()).rejects.toMatchObject({ status })
      await expect(api.login({ email: '', password: '' })).rejects.toMatchObject({ status })
      expect(dispatchEvent).not.toHaveBeenCalled()
      await expect(api.listPatients()).rejects.toMatchObject({ status })
      expect(dispatchEvent).toHaveBeenCalledOnce()
      expect(dispatchEvent.mock.calls[0]?.[0].type).toBe('kay:session-expired')
    },
  )

  it('preserva los errores 422 por campo y evita mostrar cuerpos internos 500', async () => {
    const http = createHttpClient('http://localhost:8000')
    http.defaults.adapter = async (config) => {
      throw new AxiosError(
        'Validation',
        'ERR_BAD_REQUEST',
        config,
        undefined,
        response(
          config,
          {
            message: 'Validation',
            errors: { chart_number: ['El número ya existe.'], ignored: 42 },
          },
          422,
        ),
      )
    }
    const api = new RealApiAdapter(undefined, http)
    await expect(api.createPatient(patientInput)).rejects.toMatchObject({
      status: 422,
      fields: { chart_number: ['El número ya existe.'] },
    })
    const error = normalizeError(
      new AxiosError('Private stack trace', 'ERR_BAD_RESPONSE', undefined, undefined, {
        status: 500,
        data: { message: 'Private database information' },
        statusText: '',
        headers: {},
        config: {} as InternalAxiosRequestConfig,
      }),
    )
    expect(error.message).not.toContain('Private')
    expect(error).toBeInstanceOf(AppError)
  })

  it('descarga PDFs desde el endpoint protegido y rechaza contenido distinto', async () => {
    const http = createHttpClient('http://localhost:8000')
    let lastConfig: InternalAxiosRequestConfig | undefined
    http.defaults.adapter = async (config) => {
      lastConfig = config
      return response(config, new Blob(['%PDF-1.4'], { type: 'application/pdf' }))
    }
    const api = new RealApiAdapter(undefined, http)
    const encounterId = createFixtures().encounters[0]!.id
    expect((await api.downloadDocument(encounterId, 'his')).type).toBe('application/pdf')
    expect(lastConfig?.url).toBe(`/api/v1/encounters/${encounterId}/documents/his.pdf`)
    expect(lastConfig?.responseType).toBe('blob')
    http.defaults.adapter = async (config) =>
      response(config, new Blob(['{}'], { type: 'application/json' }))
    await expect(api.downloadDocument(encounterId, 'clinical-note')).rejects.toMatchObject({
      status: 502,
    })
  })
})
