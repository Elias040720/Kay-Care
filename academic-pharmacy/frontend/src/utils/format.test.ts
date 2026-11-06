import { describe, expect, it } from 'vitest'
import { formatMoney, newIdempotencyKey, validateBuyerAlias } from './format'

describe('formatMoney', () => {
  it('formatea céntimos como soles', () => {
    expect(formatMoney(2490)).toMatch(/S\/\s?24\.90/)
    expect(formatMoney(0)).toMatch(/S\/\s?0\.00/)
  })

  it('tolera valores no numéricos', () => {
    expect(formatMoney(Number.NaN)).toMatch(/0\.00/)
  })
})

describe('validateBuyerAlias', () => {
  it('acepta alias ficticios', () => {
    expect(validateBuyerAlias('Comprador Demo 07')).toBeNull()
    expect(validateBuyerAlias('Ñandú_Feliz-3')).toBeNull()
  })

  it('rechaza documentos, teléfonos, correos y HTML', () => {
    expect(validateBuyerAlias('45879632')).toMatch(/documentos/)
    expect(validateBuyerAlias('Ana 987654321')).toMatch(/documentos/)
    expect(validateBuyerAlias('ana@correo.com')).toMatch(/Solo letras/)
    expect(validateBuyerAlias('<b>hola</b>')).toMatch(/Solo letras/)
    expect(validateBuyerAlias('ab')).toMatch(/3 caracteres/)
  })
})

describe('newIdempotencyKey', () => {
  it('genera claves únicas compatibles con la API', () => {
    const a = newIdempotencyKey()
    const b = newIdempotencyKey()
    expect(a).not.toBe(b)
    expect(a).toMatch(/^[A-Za-z0-9_-]{16,80}$/)
  })
})
