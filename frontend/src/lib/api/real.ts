import axios, { type AxiosInstance, type AxiosResponse } from 'axios'
import { z } from 'zod'
import type { ApiAdapter, DocumentKind, EncounterWrite, HisLineWrite, PatientWrite } from '@/types'
import { AppError, normalizeError } from './errors'
import {
  encounterSchema,
  envelope,
  healthSchema,
  hisLineSchema,
  hisPreviewSchema,
  hisSuggestionSchema,
  paginatedPatientsSchema,
  patientSchema,
  userSchema,
} from './schemas'

export function createHttpClient(baseURL: string): AxiosInstance {
  return axios.create({
    baseURL: baseURL.replace(/\/$/, ''),
    withCredentials: true,
    withXSRFToken: true,
    timeout: 20_000,
    headers: { Accept: 'application/json' },
  })
}

/** Only the immutable API v1 routes are used here. No automatic demo fallback. */
export class RealApiAdapter implements ApiAdapter {
  readonly http: AxiosInstance

  constructor(baseURL = 'http://localhost:8000', http?: AxiosInstance) {
    this.http = http ?? createHttpClient(baseURL)
  }

  private fail(error: unknown, protectedSession: boolean): never {
    const normalized = normalizeError(error)
    if (
      protectedSession &&
      [401, 419].includes(normalized.status) &&
      typeof window !== 'undefined'
    ) {
      window.dispatchEvent(new CustomEvent('kay:session-expired', { detail: normalized.status }))
    }
    throw normalized
  }

  private async request<T>(
    request: () => Promise<AxiosResponse<unknown>>,
    schema: z.ZodType<T>,
    protectedSession = true,
  ): Promise<T> {
    try {
      const response = await request()
      return schema.parse(response.data)
    } catch (error) {
      return this.fail(error, protectedSession)
    }
  }

  health() {
    return this.request(() => this.http.get('/api/v1/health'), healthSchema, false)
  }

  async login(input: { email: string; password: string }) {
    try {
      await this.http.get('/sanctum/csrf-cookie')
    } catch (error) {
      return this.fail(error, false)
    }
    const response = await this.request(
      () => this.http.post('/api/v1/auth/login', input),
      envelope(userSchema),
      false,
    )
    return response.data
  }

  async me() {
    return (await this.request(() => this.http.get('/api/v1/auth/me'), envelope(userSchema), false))
      .data
  }

  async logout() {
    await this.request(
      () => this.http.post('/api/v1/auth/logout'),
      z.object({ message: z.string() }),
    )
  }

  listPatients(search = '', page = 1) {
    return this.request(
      () => this.http.get('/api/v1/patients', { params: { search, page } }),
      paginatedPatientsSchema,
    )
  }

  async createPatient(input: PatientWrite) {
    return (
      await this.request(() => this.http.post('/api/v1/patients', input), envelope(patientSchema))
    ).data
  }

  async getPatient(id: string) {
    return (
      await this.request(
        () => this.http.get(`/api/v1/patients/${encodeURIComponent(id)}`),
        envelope(patientSchema),
      )
    ).data
  }

  async updatePatient(id: string, input: Partial<PatientWrite>) {
    return (
      await this.request(
        () => this.http.patch(`/api/v1/patients/${encodeURIComponent(id)}`, input),
        envelope(patientSchema),
      )
    ).data
  }

  async patientEncounters(id: string) {
    return (
      await this.request(
        () => this.http.get(`/api/v1/patients/${encodeURIComponent(id)}/encounters`),
        envelope(z.array(encounterSchema)),
      )
    ).data
  }

  async createEncounter(input: EncounterWrite) {
    return (
      await this.request(
        () => this.http.post('/api/v1/encounters', input),
        envelope(encounterSchema),
      )
    ).data
  }

  async getEncounter(id: string) {
    return (
      await this.request(
        () => this.http.get(`/api/v1/encounters/${encodeURIComponent(id)}`),
        envelope(encounterSchema),
      )
    ).data
  }

  async updateEncounter(id: string, input: Partial<EncounterWrite>) {
    return (
      await this.request(
        () => this.http.patch(`/api/v1/encounters/${encodeURIComponent(id)}`, input),
        envelope(encounterSchema),
      )
    ).data
  }

  async hisSuggestions(id: string) {
    return (
      await this.request(
        () => this.http.get(`/api/v1/encounters/${encodeURIComponent(id)}/his-suggestions`),
        envelope(z.array(hisSuggestionSchema)),
      )
    ).data
  }

  async saveHisLines(id: string, lines: HisLineWrite[]) {
    return (
      await this.request(
        () => this.http.put(`/api/v1/encounters/${encodeURIComponent(id)}/his-lines`, { lines }),
        envelope(z.array(hisLineSchema)),
      )
    ).data
  }

  async hisPreview(id: string) {
    return (
      await this.request(
        () => this.http.get(`/api/v1/encounters/${encodeURIComponent(id)}/his-preview`),
        envelope(hisPreviewSchema),
      )
    ).data
  }

  async downloadDocument(id: string, kind: DocumentKind): Promise<Blob> {
    try {
      const response = await this.http.get<Blob>(
        `/api/v1/encounters/${encodeURIComponent(id)}/documents/${kind}.pdf`,
        {
          responseType: 'blob',
          headers: { Accept: 'application/pdf, application/json' },
        },
      )
      if (
        !(response.data instanceof Blob) ||
        !response.data.type.toLowerCase().includes('application/pdf')
      ) {
        throw new AppError('El servidor no devolvió un PDF. Intenta descargarlo nuevamente.', 502)
      }
      return response.data
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.data instanceof Blob) {
        try {
          error.response.data = JSON.parse(await error.response.data.text()) as unknown
        } catch {
          // Keep the transport status; never print response contents or patient data.
        }
      }
      return this.fail(error, true)
    }
  }
}
