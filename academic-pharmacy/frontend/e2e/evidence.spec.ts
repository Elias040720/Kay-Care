import { test, expect, type Page } from '@playwright/test'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Recorrido completo de la tienda y del panel que genera la evidencia en
 * docs/evidence/. Requiere el stack en marcha y ADMIN_PASSWORD.
 */
const here = dirname(fileURLToPath(import.meta.url))
const OUT = resolve(here, '../../docs/evidence')
const NOTICE = 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES'
// axe se evalúa por CDP: no requiere relajar la CSP estricta de la página.
const axeSource = readFileSync(createRequire(import.meta.url).resolve('axe-core/axe.min.js'), 'utf8')
mkdirSync(OUT, { recursive: true })

const consoleErrors: string[] = []
const a11y: Record<string, Array<{ id: string; impact: string | null; nodes: number; targets: string[] }>> = {}

async function shot(page: Page, name: string, fullPage = false) {
  await page.waitForLoadState('networkidle')
  await page.screenshot({ path: `${OUT}/${name}.png`, fullPage })
}

async function audit(page: Page, name: string) {
  await page.evaluate(axeSource)
  const violations = await page.evaluate(async () => {
    // @ts-expect-error axe se inyecta en la página
    const result = await window.axe.run(document, { resultTypes: ['violations'] })
    return result.violations.map((v: { id: string; impact: string | null; nodes: Array<{ target: string[] }> }) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) }))
  })
  a11y[name] = violations
}

test.describe.configure({ mode: 'serial' })

test('recorrido público: catálogo → carrito → checkout → confirmación', async ({ page }) => {
  page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text()))

  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await shot(page, '01-inicio', true)
  await audit(page, 'inicio')

  await page.goto('/catalogo')
  await expect(page.getByTestId('product-card').first()).toBeVisible()
  await expect(page.getByText(NOTICE).first()).toBeVisible()
  await shot(page, '02-catalogo', true)
  await audit(page, 'catalogo')

  await page.getByTestId('catalog-search').fill('termómetro')
  await expect(page.getByTestId('results-count')).toContainText('1 producto')
  await shot(page, '03-catalogo-busqueda')

  await page.getByTestId('catalog-search').fill('')
  await page.getByTestId('filter-category-cuidado-de-la-piel').check()
  await page.getByTestId('catalog-sort').selectOption('price_desc')
  await page.getByTestId('filter-in-stock').check()
  await expect(page).toHaveURL(/categoria=cuidado-de-la-piel/)
  await expect(page.getByTestId('product-card')).toHaveCount(3)
  await shot(page, '04-catalogo-filtros-orden')

  await page.goto('/producto/vitamina-c-500-mg-masticable')
  await expect(page.getByTestId('detail-price')).toBeVisible()
  await shot(page, '05-detalle-producto')
  await audit(page, 'detalle')

  await page.getByRole('button', { name: /Aumentar cantidad/ }).click()
  await page.getByTestId('detail-add').click()
  const drawer = page.getByTestId('cart-drawer')
  await expect(drawer).toBeVisible()
  await expect(drawer.getByTestId('cart-line')).toHaveCount(1)
  await expect(page.getByTestId('go-checkout')).toBeEnabled()
  await shot(page, '06-carrito-drawer')

  await page.getByTestId('go-checkout').click()
  await expect(page).toHaveURL(/\/checkout$/)
  await expect(page.getByText(NOTICE).first()).toBeVisible()
  await page.getByTestId('buyer-alias').fill('45879632')
  await page.getByTestId('place-order').click()
  await expect(page.getByText('No ingreses documentos').first()).toBeVisible()
  await shot(page, '07-checkout-validacion')
  await audit(page, 'checkout')

  await page.getByTestId('buyer-alias').fill('Comprador Demo Evidencia')
  await page.getByTestId('accept-terms').check()
  await shot(page, '08-checkout-listo')
  await page.getByTestId('place-order').click()

  await expect(page).toHaveURL(/\/pedido\/KCF-\d{4}-\d{6}\?token=/)
  await expect(page.getByTestId('order-number')).toHaveText(/^KCF-\d{4}-\d{6}$/)
  await expect(page.getByText(NOTICE).first()).toBeVisible()
  await shot(page, '09-confirmacion', true)
  await audit(page, 'confirmacion')
})

test('panel de administración', async ({ page }) => {
  const password = process.env.ADMIN_PASSWORD
  test.skip(!password, 'Define ADMIN_PASSWORD')
  page.on('console', (m) => m.type() === 'error' && consoleErrors.push(m.text()))

  await page.goto('/admin')
  await expect(page).toHaveURL(/\/admin\/login/)
  await page.getByTestId('admin-email').fill('admin@farmacia.demo')
  await page.getByTestId('admin-password').fill('incorrecta')
  await page.getByTestId('admin-submit').click()
  await expect(page.getByRole('alert')).toContainText('Credenciales inválidas')
  await shot(page, '10-admin-login-error')

  await page.getByTestId('admin-password').fill(password!)
  await page.getByTestId('admin-submit').click()
  await expect(page.getByTestId('kpi-orders')).toBeVisible()
  await shot(page, '11-admin-dashboard', true)
  await audit(page, 'admin-dashboard')

  await page.getByRole('link', { name: 'Productos' }).click()
  await expect(page.getByTestId('admin-product-row').first()).toBeVisible()
  await shot(page, '12-admin-productos')
  await page.getByTestId('new-product').click()
  await expect(page.getByTestId('product-form')).toBeVisible()
  await shot(page, '13-admin-producto-formulario')
  await page.keyboard.press('Escape')

  await page.getByRole('link', { name: 'Stock' }).click()
  await page.getByTestId('adjust-KCF-PA-004').click()
  await page.getByTestId('adjust-amount').fill('10')
  await shot(page, '14-admin-ajuste-stock')
  await page.getByTestId('adjust-submit').click()
  await expect(page.getByText('Stock actualizado').first()).toBeVisible()
  await shot(page, '15-admin-stock-movimientos')

  await page.getByRole('link', { name: 'Pedidos' }).click()
  await expect(page.getByTestId('order-row').first()).toBeVisible()
  await shot(page, '16-admin-pedidos')
  await page.getByTestId('order-row').first().getByRole('link').click()
  await expect(page.getByTestId('admin-order-number')).toBeVisible()
  const transition = page.locator('[data-testid^="transition-"]').first()
  if (await transition.count()) {
    await transition.click()
    await page.getByTestId('confirm-transition').click()
    await expect(page.getByText('Estado actualizado').first()).toBeVisible()
  }
  await shot(page, '17-admin-pedido-detalle')
})

test('vista móvil del catálogo', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, reducedMotion: 'reduce' })
  const page = await context.newPage()
  await page.goto('/catalogo')
  await expect(page.getByTestId('product-card').first()).toBeVisible()
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)
  expect(overflow).toBe(false)
  await shot(page, '18-movil-catalogo')
  await context.close()
})

test.afterAll(() => {
  const serious = Object.fromEntries(
    Object.entries(a11y).map(([k, v]) => [k, v.filter((x) => x.impact === 'serious' || x.impact === 'critical')]),
  )
  writeFileSync(
    `${OUT}/evidence-report.json`,
    JSON.stringify({ generated_at: new Date().toISOString(), base_url: process.env.BASE_URL ?? 'http://localhost:8088', console_errors: consoleErrors, axe_violations: a11y, axe_serious_or_critical: serious }, null, 2),
  )
})
