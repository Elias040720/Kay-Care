import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { readFile } from 'node:fs/promises'

async function login(page: Page) {
  await page.goto('/')
  await page.getByRole('button', { name: 'Explorar demostración' }).click()
  await expect(page.getByRole('heading', { name: /enfermera\./ })).toBeVisible()
}
test('flujo completo: registro, edición, CRED, HIS explícito, PDF e historial', async ({
  page,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  await login(page)
  await page.getByRole('link', { name: /Pacientes/, exact: true }).click()
  await page.getByRole('button', { name: 'Página siguiente', exact: true }).click()
  await expect(page.getByText('Página 2 de 2')).toBeVisible()
  await page.getByRole('button', { name: 'Registrar paciente' }).click()
  await page.getByLabel('N.º de historia clínica *').fill('DEMO-E2E')
  await page.getByLabel('Nombres *', { exact: true }).fill('Paciente')
  await page.getByLabel('Apellidos *').fill('Ficticio E2E')
  await page.getByLabel('Fecha de nacimiento *').fill('2025-01-15')
  await page.getByRole('button', { name: 'Guardar paciente' }).click()
  await expect(page.getByRole('dialog')).not.toBeVisible()
  await page.getByRole('searchbox').fill('DEMO-E2E')
  await page.getByRole('link', { name: /Ver ficha de Paciente/ }).click()
  await page.getByRole('button', { name: 'Editar datos' }).click()
  await page.getByLabel('Localidad').fill('Localidad ficticia de prueba')
  await page.getByRole('button', { name: 'Guardar paciente' }).click()
  await expect(page.getByText('Localidad ficticia de prueba', { exact: true })).toBeVisible()
  await page.getByRole('link', { name: 'Nueva atención', exact: true }).last().click()
  await page.getByLabel('Peso').fill('8.2')
  await page.getByLabel('Talla').fill('70')
  await page
    .getByLabel('Notas clínicas')
    .fill('Prueba automatizada con datos completamente ficticios.')
  await page.getByRole('button', { name: 'Guardar y revisar HIS' }).click()
  const activities = page.getByRole('checkbox')
  await expect(activities).toHaveCount(3)
  for (const checkbox of await activities.all()) await expect(checkbox).not.toBeChecked()
  await activities.first().check()
  await expect(page.getByLabel('Valor LAB')).toHaveValue('')
  await page.getByLabel('Tipo de diagnóstico').selectOption('D')
  await page.getByRole('button', { name: 'Guardar actividades y revisar' }).click()
  await expect(page.getByRole('heading', { name: 'Vista previa HIS' })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'DEMO-CRED', exact: true })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'DEMO-HB', exact: true })).toHaveCount(0)
  const downloadEvent = page.waitForEvent('download')
  await page.getByRole('button', { name: /Registro HIS PDF/ }).click()
  const download = await downloadEvent
  expect(download.suggestedFilename()).toContain('DEMO')
  const path = await download.path()
  expect(path).toBeTruthy()
  const pdf = await readFile(path!, 'utf8')
  expect(pdf.startsWith('%PDF')).toBe(true)
  expect(pdf).toContain('SIN VALIDEZ OFICIAL')
  await page.getByRole('button', { name: 'Finalizar revisión' }).click()
  await expect(page).toHaveURL(/\/atenciones\/[a-f0-9-]+$/)
  await page
    .getByRole('link', { name: /Continuar|Editar/, exact: false })
    .last()
    .click()
  await page.getByLabel('Peso').fill('8.3')
  await page.getByRole('button', { name: 'Guardar y revisar HIS' }).click()
  await expect(page.getByRole('checkbox').first()).toBeChecked()
  await page.getByRole('button', { name: 'Guardar actividades y revisar' }).click()
  await expect(page.getByText('8.3 kg', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Finalizar revisión' }).click()
  await page.getByRole('link', { name: /Ficha del paciente/ }).click()
  await expect(page.getByRole('link', { name: /Control de crecimiento/ })).toHaveCount(1)
  const storage = await page.evaluate(() => ({
    local: localStorage.length,
    session: sessionStorage.length,
  }))
  expect(storage).toEqual({ local: 0, session: 0 })
  expect(errors).toEqual([])
})
test('protección de rutas, cierre de sesión y reinicio de demo', async ({ page }) => {
  await page.goto('/pacientes')
  await expect(page).toHaveURL(/acceso/)
  await page.getByRole('button', { name: 'Explorar demostración' }).click()
  await expect(page).toHaveURL(/pacientes/)
  await page.getByRole('button', { name: 'Cerrar sesión' }).click()
  await expect(page.getByRole('button', { name: 'Explorar demostración' })).toBeVisible()
  await page.goto('/documentos')
  await expect(page).toHaveURL(/acceso/)
})
test('drawer accesible, validación, Escape y protección de cambios', async ({ page }) => {
  await login(page)
  await page.getByRole('link', { name: /Registrar paciente/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await page.getByRole('button', { name: 'Guardar paciente' }).click()
  await expect(page.getByLabel('N.º de historia clínica *')).toBeFocused()
  await page.getByLabel('Nombres *', { exact: true }).fill('No descartar')
  page.once('dialog', (dialog) => dialog.dismiss())
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await expect(page.getByLabel('Nombres *', { exact: true })).toHaveValue('No descartar')
  page.once('dialog', (dialog) => dialog.accept())
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()
  await expect(page.getByRole('button', { name: 'Registrar paciente' })).toBeFocused()
})
test('escritorio, móvil 360px, teclado, reduced motion y accesibilidad', async ({ page }) => {
  await login(page)
  await expect(page.getByText('Atenciones consultadas', { exact: true })).toBeVisible()
  await page.screenshot({ path: 'qa-output/dashboard-desktop.png', fullPage: true })
  let results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()
  expect(
    results.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) })),
  ).toEqual([])
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.setViewportSize({ width: 360, height: 800 })
  await expect
    .poll(() => page.locator('.sidebar').evaluate((el) => el.getBoundingClientRect().right))
    .toBeLessThanOrEqual(1)
  await page.screenshot({ path: 'qa-output/dashboard-mobile.png', fullPage: true })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.getByRole('button', { name: 'Abrir menú' }).click()
  await page.getByRole('link', { name: 'Pacientes', exact: true }).click()
  await page.getByRole('button', { name: 'Registrar paciente' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.keyboard.press('Tab')
  expect(await page.evaluate(() => !!document.activeElement?.closest('dialog'))).toBe(true)
  results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()
  expect(
    results.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) })),
  ).toEqual([])
  const duration = await page
    .getByRole('dialog')
    .evaluate((el) => getComputedStyle(el).animationDuration)
  expect(parseFloat(duration)).toBeLessThan(0.01)
})
test('flujo CRED en móvil y accesibilidad de HIS y revisión', async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 800 })
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/')
  await page.screenshot({ path: 'qa-output/login-mobile.png', fullPage: true })
  const loginA11y = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
    .analyze()
  expect(
    loginA11y.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) })),
  ).toEqual([])
  await page.getByRole('button', { name: 'Explorar demostración' }).click()
  await page.getByRole('link', { name: 'Nueva atención', exact: true }).click()
  await page.getByRole('radio').first().check()
  await page.getByRole('button', { name: 'Continuar con la evaluación' }).click()
  await expect(page.getByLabel('Peso')).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.screenshot({ path: 'qa-output/cred-mobile.png', fullPage: true })
  await page.getByRole('button', { name: 'Guardar y revisar HIS' }).click()
  await page.getByRole('checkbox').first().check()
  let result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()
  expect(result.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) }))).toEqual(
    [],
  )
  await page.screenshot({ path: 'qa-output/his-mobile.png', fullPage: true })
  await page.getByRole('button', { name: 'Guardar actividades y revisar' }).click()
  await expect(page.getByRole('heading', { name: 'Vista previa HIS' })).toBeVisible()
  result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()
  expect(result.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) }))).toEqual(
    [],
  )
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  )
  await page.screenshot({ path: 'qa-output/review-mobile.png', fullPage: true })
})
test('nueva atención después de reanudar y protección de cambios de query', async ({ page }) => {
  await login(page)
  await page.getByRole('link', { name: /LE Lucía Ejemplo/ }).click()
  await page.getByRole('link', { name: 'Editar atención' }).click()
  await page.getByLabel('Peso').fill('7.4')
  page.once('dialog', (dialog) => dialog.dismiss())
  await page.getByRole('link', { name: 'Nueva atención', exact: true }).click()
  await expect(page.getByLabel('Peso')).toHaveValue('7.4')
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('link', { name: 'Nueva atención', exact: true }).click()
  await expect(page.getByRole('heading', { name: '¿A quién atenderemos?' })).toBeVisible()
  await expect(page.getByRole('radio').first()).not.toBeChecked()
})
test('documentación reinicia paginación al buscar', async ({ page }) => {
  await login(page)
  await page.getByRole('link', { name: 'Documentación', exact: true }).click()
  await page.getByRole('button', { name: 'Siguiente', exact: true }).click()
  await page.getByRole('searchbox').fill('Lucía')
  await page.getByRole('button', { name: /Lucía Ejemplo/ }).click()
  await expect(page.getByRole('link', { name: /Atención CRED/ })).toBeVisible()
})
