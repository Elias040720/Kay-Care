#!/usr/bin/env node
// Prueba de concurrencia del checkout contra el stack real (PostgreSQL).
// Lanza N compras simultáneas de 1 unidad sobre un producto con stock S
// (N > S) y comprueba que se crean exactamente S pedidos y el stock queda en 0.
//
//   BASE_URL=http://localhost:8088 node scripts/concurrency-check.mjs [slug]
// Nota: el límite de checkout es 10/min por IP; N se acota a 10.

const BASE = process.env.BASE_URL ?? 'http://localhost:8088'
const API = `${BASE}/api/v1/store`
const slug = process.argv[2] ?? 'enjuague-bucal-sin-alcohol'

async function csrf() {
  const res = await fetch(`${API}/sanctum/csrf-cookie`, { headers: { Referer: `${BASE}/` } })
  const cookies = res.headers.getSetCookie()
  const token = decodeURIComponent(cookies.find((c) => c.startsWith('XSRF-TOKEN='))?.split(';')[0].split('=')[1] ?? '')
  return { cookie: cookies.map((c) => c.split(';')[0]).join('; '), token }
}

const product = (await (await fetch(`${API}/products/${slug}`)).json()).data
const stock = product.stock
if (stock < 1 || stock > 8) {
  console.error(`El producto ${slug} debe tener entre 1 y 8 unidades (tiene ${stock}).`)
  process.exit(2)
}
const attempts = Math.min(10, stock + 5)
const { cookie, token } = await csrf()

console.log(`Producto ${product.sku} con stock ${stock}: ${attempts} compras simultáneas de 1 unidad…`)
const results = await Promise.all(
  Array.from({ length: attempts }, (_, i) =>
    fetch(`${API}/checkout`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Origin: BASE,
        Referer: `${BASE}/checkout`,
        Cookie: cookie,
        'X-XSRF-TOKEN': token,
        'Idempotency-Key': `concurrency-${Date.now()}-${i}-kcf`,
      },
      body: JSON.stringify({ buyer_alias: `Concurrencia ${i + 1}`, accept_demo_terms: true, items: [{ product_id: product.id, quantity: 1 }] }),
    }).then(async (r) => ({ status: r.status, code: (await r.json().catch(() => ({}))).code })),
  ),
)

const created = results.filter((r) => r.status === 201).length
const conflicts = results.filter((r) => r.status === 409 && r.code === 'insufficient_stock').length
const after = (await (await fetch(`${API}/products/${slug}`)).json()).data.stock
const summary = { stock_inicial: stock, intentos: attempts, creados: created, rechazados_sin_stock: conflicts, otros: results.filter((r) => ![201, 409].includes(r.status)).map((r) => r.status), stock_final: after }
console.log(JSON.stringify(summary, null, 2))

const ok = created === stock && conflicts === attempts - stock && after === 0
console.log(ok ? '✔ Sin sobreventa: el bloqueo de filas funcionó.' : '✘ Resultado inesperado.')
process.exit(ok ? 0 : 1)
