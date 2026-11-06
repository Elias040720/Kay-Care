/** Acceso tolerante a fallos a Web Storage (modo privado, cuotas, bloqueos). */
export function readJson<T>(storage: 'local' | 'session', key: string, fallback: T): T {
  try {
    const raw = (storage === 'local' ? localStorage : sessionStorage).getItem(key)
    return raw ? (JSON.parse(raw) as T) : fallback
  } catch {
    return fallback
  }
}

export function writeJson(storage: 'local' | 'session', key: string, value: unknown): void {
  try {
    ;(storage === 'local' ? localStorage : sessionStorage).setItem(key, JSON.stringify(value))
  } catch {
    /* sin almacenamiento disponible: el estado vive solo en memoria */
  }
}

export function removeKey(storage: 'local' | 'session', key: string): void {
  try {
    ;(storage === 'local' ? localStorage : sessionStorage).removeItem(key)
  } catch {
    /* ignorado */
  }
}
