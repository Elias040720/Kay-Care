// Convierte salidas reales de comandos (archivos .txt) en imágenes PNG para
// el informe, y captura la página pública de GitHub Actions.
//   node render-cli-evidence.mjs <carpeta-con-txt>
// Cada imagen indica que es la salida textual del comando y la fecha.
// Requiere @playwright/test instalado en ../../frontend (Chromium).
import { readFileSync, existsSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const out = resolve(here, '../evidence')
const src = process.argv[2]
if (!src) throw new Error('Uso: node render-cli-evidence.mjs <carpeta-con-txt>')
const { chromium } = createRequire(resolve(here, '../../frontend/package.json'))('@playwright/test')

const items = [
  ['docker.txt', '23-docker-compose.png', 'Docker Compose: servicios, usuarios sin root, salud de la API y volúmenes'],
  ['terraform.txt', '24-terraform-validate.png', 'Terraform: formato y validación de los entornos DEMO y HA (sin plan ni apply)'],
  ['tests.txt', '25-pruebas-automatizadas.png', 'Pruebas automatizadas: backend (PHPUnit) y frontend (Vitest)'],
  ['clinico.txt', '26-regresion-clinica.png', 'Aislamiento: sistema clínico sin cambios y su batería de pruebas'],
]

const date = new Date().toLocaleString('es-PE', { timeZone: 'America/Lima', dateStyle: 'long', timeStyle: 'short' })
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1180, height: 400 }, deviceScaleFactor: 2 })
for (const [file, png, title] of items) {
  const path = resolve(src, file)
  if (!existsSync(path)) continue
  const text = readFileSync(path, 'utf8').trimEnd()
  await page.setContent(`<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
    body{margin:0;padding:24px;background:#F1F6F2;font-family:"Segoe UI",Arial,sans-serif}
    .win{background:#18201D;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .bar{background:#2B3531;color:#DFEBE2;padding:10px 16px;font-size:13px;display:flex;gap:8px;align-items:center}
    .dot{width:11px;height:11px;border-radius:50%;display:inline-block}
    pre{margin:0;padding:18px 20px;color:#E6F2EA;font:13px/1.55 Consolas,"Cascadia Mono",monospace;white-space:pre-wrap;word-break:break-all}
    .cap{margin-top:10px;font-size:12px;color:#4E5A55}
  </style></head><body><div class="win"><div class="bar"><span class="dot" style="background:#E5735B"></span><span class="dot" style="background:#E7B54A"></span><span class="dot" style="background:#5FB37D"></span>&nbsp;${esc(title)}</div><pre>${esc(text)}</pre></div>
  <div class="cap">Salida textual real del comando, capturada en el equipo de desarrollo el ${esc(date)} (hora de Lima). Kay Care Farmacia — proyecto académico.</div></body></html>`)
  const height = await page.evaluate(() => document.body.scrollHeight)
  await page.setViewportSize({ width: 1180, height })
  await page.screenshot({ path: resolve(out, png), fullPage: true })
  console.log('✔', png)
}

// GitHub Actions (repositorio público, sin iniciar sesión).
const runs = [
  ['https://github.com/Elias040720/Kay-Care/actions/workflows/pharmacy-ci.yml', '27-github-actions-ci.png'],
  ['https://github.com/Elias040720/Kay-Care/actions/workflows/pharmacy-deploy.yml', '28-github-actions-deploy-sin-ejecuciones.png'],
]
for (const [url, png] of runs) {
  const gh = await browser.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1, locale: 'es-PE' })
  await gh.goto(url, { waitUntil: 'networkidle' })
  await gh.screenshot({ path: resolve(out, png) })
  console.log('✔', png)
  await gh.close()
}
await browser.close()
