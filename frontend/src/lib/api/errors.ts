import axios from 'axios'
import { ZodError } from 'zod'

export class AppError extends Error {
  constructor(
    message: string,
    public readonly status = 0,
    public readonly fields: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'AppError'
  }
}

function fieldErrors(value: unknown): Record<string, string[]> {
  if (!value || typeof value !== 'object') return {}
  return Object.fromEntries(
    Object.entries(value).filter(
      (entry): entry is [string, string[]] =>
        Array.isArray(entry[1]) && entry[1].every((message) => typeof message === 'string'),
    ),
  )
}

export function normalizeError(error: unknown): AppError {
  if (error instanceof AppError) return error
  if (error instanceof ZodError) {
    return new AppError(
      'La respuesta del servidor no tiene el formato esperado. Intenta de nuevo o contacta a soporte.',
      502,
    )
  }
  if (axios.isAxiosError(error)) {
    const status = error.response?.status ?? 0
    const body: unknown = error.response?.data
    const fields =
      body && typeof body === 'object' && 'errors' in body ? fieldErrors(body.errors) : {}
    const messages: Record<number, string> = {
      0: 'No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.',
      401: 'Tu sesión no está disponible. Vuelve a iniciar sesión.',
      403: 'No tienes permiso para realizar esta acción.',
      404: 'No encontramos este registro. Vuelve al listado para actualizarlo.',
      419: 'Tu sesión venció. Vuelve a iniciar sesión para continuar.',
      422: 'Revisa los campos indicados antes de continuar.',
      429: 'Hay demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
    }
    return new AppError(
      messages[status] ?? 'No pudimos completar la solicitud. Puedes volver a intentarlo.',
      status,
      fields,
    )
  }
  return new AppError('No pudimos completar esta acción. Inténtalo de nuevo.')
}
