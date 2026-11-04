import { test, expect, type Page } from '@playwright/test'
import { readFile } from 'node:fs/promises'
import { execFileSync } from 'node:child_process'

const api = 'http://localhost:8001'
const origin = 'http://localhost:5175'
const email = process.env.KAYCARE_TEST_EMAIL
const password = process.env.KAYCARE_TEST_PASSWORD
const database = process.env.KAYCARE_TEST_DATABASE
test.beforeAll(() => {
  if (!email || !password || !database)
    throw new Error(
      'Ejecuta scripts/test-integration.ps1 para usar SQLite aislado y credenciales efímeras.',
    )
})
async function login(page: Page) {
  await page.goto('/acceso')
  await page.getByLabel('Correo electrónico').fill(email!)
  await page.getByLabel('Contraseña', { exact: true }).fill(password!)
  const response = page.waitForResponse((r) => r.url().endsWith('/api/v1/auth/login'))
  await page.getByRole('button', { name: 'Ingresar a Kay Care' }).click()
  expect((await response).status()).toBe(200)
  await expect(page).not.toHaveURL(/acceso/)
}

test('Laravel real: CORS, CSRF, credenciales incorrectas, sesión y logout', async ({
  page,
  request,
}) => {
  const health = await request.get(`${api}/api/v1/health`)
  expect(health.ok()).toBe(true)
  expect(
    (
      await request.get(`${api}/api/v1/patients`, {
        headers: { Accept: 'application/json', Origin: origin },
      })
    ).status(),
  ).toBe(401)
  expect(
    (
      await request.post(`${api}/api/v1/auth/login`, {
        headers: { Accept: 'application/json', Origin: origin },
        data: { email, password },
      })
    ).status(),
  ).toBe(419)
  await page.goto('/acceso')
  await page.getByLabel('Correo electrónico').fill(email!)
  await page.getByLabel('Contraseña', { exact: true }).fill('incorrecta-solo-prueba')
  const invalid = page.waitForResponse((r) => r.url().endsWith('/api/v1/auth/login'))
  await page.getByRole('button', { name: 'Ingresar a Kay Care' }).click()
  expect((await invalid).status()).toBe(422)
  await expect(page.getByText('Las credenciales no son válidas.')).toBeVisible()
  await expect(page.getByLabel('Correo electrónico')).toBeFocused()
  await login(page)
  const response = await page.request.get(`${api}/api/v1/auth/me`, {
    headers: { Accept: 'application/json', Origin: origin },
  })
  expect(response.status()).toBe(200)
  expect(response.headers()['access-control-allow-origin']).toBe(origin)
  expect(response.headers()['access-control-allow-credentials']).toBe('true')
  const cookies = await page.context().cookies(api)
  expect(cookies.find((cookie) => cookie.name === 'kaycare_integration_session')?.httpOnly).toBe(
    true,
  )
  expect(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.httpOnly).toBe(false)
  await page.getByRole('button', { name: 'Cerrar sesión' }).click()
  await expect(page).toHaveURL(/acceso/)
  expect(
    (
      await page.request.get(`${api}/api/v1/auth/me`, {
        headers: { Accept: 'application/json', Origin: origin },
      })
    ).status(),
  ).toBe(401)
})

test('Laravel real: paciente, edición, CRED, HIS confirmado, dos PDF y persistencia SQLite', async ({
  page,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  await login(page)
  await expect(page.getByText('DEMO', { exact: true })).toBeVisible()
  const chart = `DEMO-INT-${Date.now()}`
  await page.getByRole('link', { name: 'Pacientes', exact: true }).click()
  await page.getByRole('button', { name: 'Registrar paciente' }).click()
  await page.getByLabel('N.º de historia clínica *').fill(chart)
  await page.getByLabel('Nombres *', { exact: true }).fill('Paciente')
  await page.getByLabel('Apellidos *').fill('Ficticio Integracion')
  const birth = new Date()
  birth.setUTCFullYear(birth.getUTCFullYear() - 2)
  await page.getByLabel('Fecha de nacimiento *').fill(birth.toISOString().slice(0, 10))
  const savedPatient = page.waitForResponse(
    (r) => r.url().endsWith('/api/v1/patients') && r.request().method() === 'POST',
  )
  await page.getByRole('button', { name: 'Guardar paciente' }).click()
  const patientResponse = await savedPatient
  expect(patientResponse.status()).toBe(201)
  const patient = (await patientResponse.json()).data
  await expect(page.getByRole('dialog')).not.toBeVisible()
  const csrf = (await page.context().cookies(api)).find((cookie) => cookie.name === 'XSRF-TOKEN')!
  const duplicate = await page.request.post(`${api}/api/v1/patients`, {
    headers: {
      Origin: origin,
      Accept: 'application/json',
      'X-XSRF-TOKEN': decodeURIComponent(csrf.value),
    },
    data: {
      chart_number: chart,
      first_name: 'Paciente',
      last_name: 'Ficticio Integracion',
      birth_date: patient.birth_date,
      sex: 'X',
    },
  })
  expect(duplicate.status()).toBe(422)
  expect((await duplicate.json()).errors.chart_number).toBeTruthy()
  await page.getByRole('searchbox').fill(chart)
  await page.getByRole('link', { name: /Ver ficha de Paciente/ }).click()
  await page.getByRole('button', { name: 'Editar datos' }).click()
  await page.getByLabel('Localidad').fill('Localidad ficticia de integración')
  await page.getByRole('button', { name: 'Guardar paciente' }).click()
  await expect(page.getByText('Localidad ficticia de integración', { exact: true })).toBeVisible()
  await page.getByRole('link', { name: 'Nueva atención', exact: true }).last().click()
  await page.getByLabel('Peso').fill('8.2')
  await page.getByLabel('Talla').fill('70')
  await page.getByLabel('Hemoglobina').fill('12.1')
  await page
    .getByLabel('Notas clínicas')
    .fill('Prueba de integración. Información completamente ficticia.')
  const savedEncounter = page.waitForResponse(
    (r) => r.url().endsWith('/api/v1/encounters') && r.request().method() === 'POST',
  )
  const suggestionsResponse = page.waitForResponse((r) => r.url().endsWith('/his-suggestions'))
  await page.getByRole('button', { name: 'Guardar y revisar HIS' }).click()
  const encounterResponse = await savedEncounter
  expect(encounterResponse.status()).toBe(201)
  const encounter = (await encounterResponse.json()).data
  const suggestions = (await (await suggestionsResponse).json()).data
  expect(suggestions.length).toBeGreaterThan(1)
  expect(suggestions.every((item: { code: string }) => item.code.startsWith('DEMO-'))).toBe(true)
  const choices = page.getByRole('checkbox')
  await expect(choices).toHaveCount(suggestions.length)
  for (const checkbox of await choices.all()) await expect(checkbox).not.toBeChecked()
  const controlIndex = suggestions.findIndex(
    (item: { code: string }) => item.code === 'DEMO-CRED-1A4',
  )
  expect(controlIndex).toBeGreaterThanOrEqual(0)
  await choices.nth(controlIndex).check()
  await expect(page.getByLabel('Valor LAB')).toHaveValue('')
  await page.getByLabel('Tipo de diagnóstico').selectOption('D')
  await page.getByLabel('Valor LAB').fill('1')
  const linesResponse = page.waitForResponse(
    (r) => r.url().endsWith('/his-lines') && r.request().method() === 'PUT',
  )
  await page.getByRole('button', { name: 'Guardar actividades y revisar' }).click()
  const lines = await linesResponse
  expect(lines.status()).toBe(200)
  expect((await lines.json()).data).toHaveLength(1)
  await expect(page.getByRole('heading', { name: 'Vista previa HIS' })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'DEMO-CRED-1A4', exact: true })).toBeVisible()
  await expect(page.getByRole('cell', { name: 'DEMO-HB', exact: true })).toHaveCount(0)
  await expect(page.getByText('Consistencia estructural comprobada', { exact: true })).toBeVisible()
  for (const label of [/Resumen clínico PDF/, /Registro HIS PDF/]) {
    const downloading = page.waitForEvent('download')
    const pdfResponse = page.waitForResponse((r) => r.url().endsWith('.pdf'))
    await page.getByRole('button', { name: label }).click()
    const download = await downloading
    const response = await pdfResponse
    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('application/pdf')
    expect(response.headers()['content-disposition']).toContain('demo')
    expect(download.suggestedFilename()).toContain('DEMO')
    const bytes = await readFile((await download.path())!)
    expect(bytes.subarray(0, 5).toString()).toBe('%PDF-')
  }
  await page.getByRole('button', { name: 'Finalizar revisión' }).click()
  await expect(page).toHaveURL(new RegExp(`/atenciones/${encounter.id}$`))
  const detailUrl = page.url()
  await page.reload()
  await expect(page.getByText('8.2 kg', { exact: true })).toBeVisible()
  await page.getByRole('link', { name: /Ficha del paciente/ }).click()
  await expect(page.getByRole('link', { name: /Control de crecimiento/ })).toHaveCount(1)
  await page.getByRole('button', { name: 'Cerrar sesión' }).click()
  await expect(page).toHaveURL(/acceso/)
  await login(page)
  await page.goto(detailUrl)
  await expect(page.getByRole('cell', { name: 'DEMO-CRED-1A4', exact: true })).toBeVisible()
  const preview = await page.request.get(`${api}/api/v1/encounters/${encounter.id}/his-preview`, {
    headers: { Origin: origin, Accept: 'application/json' },
  })
  const data = (await preview.json()).data
  expect(data.validation_scope).toBe('structural')
  expect(data.encounter.status).toBe('draft')
  expect(data.patient.id).toBe(patient.id)
  expect(data.lines).toHaveLength(1)
  // Read persisted rows from a separate PHP process, outside browser memory/session.
  const persisted = execFileSync(
    process.env.PHP_BINARY || 'php',
    [
      '-r',
      '$db = new PDO("sqlite:" . getenv("KAYCARE_TEST_DATABASE")); $q = $db->prepare("SELECT COUNT(*) FROM his_lines WHERE encounter_id = ?"); $q->execute([$argv[1]]); echo $q->fetchColumn();',
      encounter.id,
    ],
    { encoding: 'utf8', windowsHide: true },
  )
  expect(persisted).toBe('1')
  expect(
    await page.evaluate(() => ({ local: localStorage.length, session: sessionStorage.length })),
  ).toEqual({ local: 0, session: 0 })
  await page.screenshot({ path: 'qa-output/laravel-review-desktop.png', fullPage: true })
  expect(errors).toEqual([])
})
