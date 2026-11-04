export const fullName = (p: { first_name: string; last_name: string }) =>
  `${p.first_name} ${p.last_name}`
export const initials = (name: string) =>
  name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((s) => s[0])
    .join('')
    .toUpperCase()
export function todayInPeru() {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Lima',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date())
}
export function nowLocalInput() {
  const parts = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'America/Lima',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(new Date())
  return parts.replace(' ', 'T')
}
export const toPeruISO = (local: string) => `${local}:00-05:00`
export function dateLabel(value: string, withTime = false) {
  const date = new Date(value.length === 10 ? `${value}T12:00:00-05:00` : value)
  if (Number.isNaN(date.getTime())) return 'Fecha no disponible'
  return new Intl.DateTimeFormat('es-PE', {
    timeZone: 'America/Lima',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(date)
}
export function ageAt(birth: string, at = todayInPeru()): string {
  const dateOnly = /(?:Z|[+-]\d{2}:\d{2})$/i.test(at)
    ? new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Lima',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).format(new Date(at))
    : at.slice(0, 10)
  if (!/^\d{4}-\d{2}-\d{2}$/.test(birth) || birth > dateOnly) return 'Fecha por revisar'
  const [by = 0, bm = 1, bd = 1] = birth.split('-').map(Number)
  const [ay = 0, am = 1, ad = 1] = dateOnly.split('-').map(Number)
  let months = (ay - by) * 12 + am - bm - (ad < bd ? 1 : 0)
  if (months < 1) {
    const days = Math.floor(
      (Date.parse(`${dateOnly}T00:00:00Z`) - Date.parse(`${birth}T00:00:00Z`)) / 86400000,
    )
    return `${days} ${days === 1 ? 'día' : 'días'}`
  }
  const years = Math.floor(months / 12)
  months %= 12
  return [
    years ? `${years} ${years === 1 ? 'año' : 'años'}` : '',
    months ? `${months} ${months === 1 ? 'mes' : 'meses'}` : '',
  ]
    .filter(Boolean)
    .join(', ')
}
