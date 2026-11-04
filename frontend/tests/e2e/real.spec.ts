import { test, expect, type Page } from '@playwright/test'
const user = {
  id: '00000000-0000-4000-8000-000000000001',
  name: 'Profesional ficticia',
  email: 'test@example.test',
  role: 'nurse',
}
const patient = {
  id: '10000000-0000-4000-8000-000000000001',
  chart_number: 'PRUEBA-REAL',
  first_name: 'Paciente',
  last_name: 'Contrato ficticio',
  birth_date: '2025-01-01',
  sex: 'X',
  document_type: null,
  document_number: null,
  locality: null,
}
async function server(
  page: Page,
  state: { signedIn: boolean; status: number },
  requests: string[],
) {
  await page.route('http://localhost:8000/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    requests.push(`${request.method()} ${path}`)
    const headers = {
      'Access-Control-Allow-Origin': 'http://localhost:5174',
      'Access-Control-Allow-Credentials': 'true',
      'Access-Control-Allow-Headers': 'Content-Type, Accept, X-XSRF-TOKEN',
      'Access-Control-Allow-Methods': 'GET, POST, PATCH, PUT, OPTIONS',
    }
    if (request.method() === 'OPTIONS' || path === '/sanctum/csrf-cookie')
      return route.fulfill({ status: 204, headers })
    if (path === '/api/v1/auth/login') {
      state.signedIn = true
      return route.fulfill({ json: { data: user }, headers })
    }
    if (path === '/api/v1/auth/me')
      return route.fulfill({
        status: state.signedIn ? 200 : 401,
        json: state.signedIn ? { data: user } : { message: 'Unauthenticated.' },
        headers,
      })
    if (path === '/api/v1/patients' && request.method() === 'POST')
      return route.fulfill({
        status: 422,
        json: {
          message: 'Invalid.',
          errors: { chart_number: ['Este número de historia clínica ya existe.'] },
        },
        headers,
      })
    if (state.status !== 200)
      return route.fulfill({ status: state.status, json: { message: 'Server error' }, headers })
    if (path === '/api/v1/patients')
      return route.fulfill({
        json: { data: [patient], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } },
        headers,
      })
    if (path.endsWith('/encounters')) return route.fulfill({ json: { data: [] }, headers })
    return route.fulfill({ status: 404, json: { message: 'Not found' }, headers })
  })
}
async function login(page: Page) {
  await page.goto('/acceso')
  await page.getByLabel('Correo electrónico').fill('test@example.test')
  await page.getByLabel('Contraseña', { exact: true }).fill('ficticia')
  await page.getByRole('button', { name: 'Ingresar a Kay Care' }).click()
}
test('API real: CSRF, error 422, error 500 recuperable, sin fallback demo', async ({ page }) => {
  const state = { signedIn: false, status: 200 }
  const requests: string[] = []
  await server(page, state, requests)
  await login(page)
  await expect(page.getByRole('heading', { name: /Profesional\./ })).toBeVisible()
  expect(requests.indexOf('GET /sanctum/csrf-cookie')).toBeLessThan(
    requests.indexOf('POST /api/v1/auth/login'),
  )
  await expect(page.getByText('DEMO', { exact: true })).toHaveCount(0)
  await page.getByRole('link', { name: 'Pacientes', exact: true }).click()
  await page.getByRole('button', { name: 'Registrar paciente' }).click()
  await page.getByLabel('N.º de historia clínica *').fill('PRUEBA-REAL')
  await page.getByLabel('Nombres *', { exact: true }).fill('Ficticio')
  await page.getByLabel('Apellidos *').fill('Prueba')
  await page.getByLabel('Fecha de nacimiento *').fill('2025-01-01')
  await page.getByRole('button', { name: 'Guardar paciente' }).click()
  await expect(page.getByText('Este número de historia clínica ya existe.')).toBeVisible()
  await expect(page.getByLabel('Nombres *', { exact: true })).toHaveValue('Ficticio')
  page.once('dialog', (d) => d.accept())
  await page.getByRole('button', { name: 'Cancelar', exact: true }).click()
  state.status = 500
  await page.getByRole('searchbox').fill('fallo')
  await expect(
    page.getByRole('heading', { name: 'No pudimos cargar la información' }),
  ).toBeVisible()
  await expect(page.getByText('Lucía Ejemplo')).toHaveCount(0)
  state.status = 200
  await page.getByRole('button', { name: 'Volver a intentar' }).click()
  await expect(page.getByText('Paciente Contrato ficticio', { exact: true })).toBeVisible()
})
for (const status of [401, 419])
  test(`API real: sesión ${status} redirige sin exponer datos`, async ({ page }) => {
    const state = { signedIn: false, status: 200 }
    await server(page, state, [])
    await login(page)
    await page.getByRole('link', { name: 'Pacientes', exact: true }).click()
    await expect(page.getByText('Paciente Contrato ficticio', { exact: true })).toBeVisible()
    state.status = status
    await page.getByRole('searchbox').fill('sesión vencida')
    await expect(page).toHaveURL(/acceso/)
    await expect(page.getByText(/Tu sesión ha expirado/)).toBeVisible()
    await expect(page.getByText('Paciente Contrato ficticio', { exact: true })).toHaveCount(0)
  })
