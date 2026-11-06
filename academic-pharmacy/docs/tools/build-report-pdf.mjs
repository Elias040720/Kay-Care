// Convierte docs/report/informe.md en docs/report/informe.pdf.
//   cd academic-pharmacy/docs/tools && npm install && npm run pdf
// Requiere que el frontend tenga @playwright/test instalado y Chromium
// descargado (npx playwright install chromium).
import { readFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { marked } from 'marked'

const here = dirname(fileURLToPath(import.meta.url))
const reportDir = resolve(here, '../report')
const requireFromFrontend = createRequire(resolve(here, '../../frontend/package.json'))
const { chromium } = requireFromFrontend('@playwright/test')

const markdown = readFileSync(resolve(reportDir, 'informe.md'), 'utf8')
const body = marked.parse(markdown, { gfm: true })

const html = `<!doctype html>
<html lang="es"><head><meta charset="utf-8">
<base href="${pathToFileURL(reportDir + '/').href}">
<title>Kay Care Farmacia — Informe</title>
<style>
  @page { size: A4; margin: 22mm 18mm 20mm; }
  body { font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 10.5pt; line-height: 1.5; color: #18201D; }
  h1, h2, h3 { font-family: Georgia, "Times New Roman", serif; color: #2E4638; }
  h1 { font-size: 22pt; line-height: 1.2; }
  h2 { font-size: 14pt; margin-top: 1.6em; border-bottom: 1px solid #C1D8C8; padding-bottom: 4px; page-break-after: avoid; }
  table { border-collapse: collapse; width: 100%; margin: 0.8em 0; font-size: 9pt; page-break-inside: avoid; }
  th, td { border: 1px solid #DFEBE2; padding: 4px 6px; vertical-align: top; text-align: left; }
  th { background: #F1F6F2; }
  code { font-family: Consolas, monospace; font-size: 9pt; background: #F2EBDF; padding: 0 3px; border-radius: 3px; }
  pre { background: #F2EBDF; padding: 8px; border-radius: 6px; font-size: 8.5pt; }
  pre code { background: none; }
  blockquote { margin: 0.8em 0; padding: 6px 12px; border-left: 4px solid #8A6414; background: #FBF1D9; }
  img { max-width: 100%; max-height: 230mm; display: block; margin: 0.8em auto; page-break-inside: avoid; }
  hr { border: 0; border-top: 1px solid #E6DCCB; margin: 1.5em 0; }
  .portada { text-align: center; min-height: 240mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }
  .portada h1 { margin: 1.2em 0; }
</style></head><body>${body}</body></html>`

const htmlPath = resolve(reportDir, '.informe.tmp.html')
writeFileSync(htmlPath, html)

const browser = await chromium.launch()
const page = await browser.newPage()
await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'networkidle' })
await page.pdf({
  path: resolve(reportDir, 'informe.pdf'),
  format: 'A4',
  printBackground: true,
  displayHeaderFooter: true,
  headerTemplate: '<div style="font-size:7pt;width:100%;text-align:center;color:#59645F">Kay Care Farmacia — Proyecto académico — tienda demostrativa</div>',
  footerTemplate: '<div style="font-size:7pt;width:100%;text-align:center;color:#59645F"><span class="pageNumber"></span> / <span class="totalPages"></span></div>',
  margin: { top: '22mm', bottom: '20mm', left: '18mm', right: '18mm' },
})
await browser.close()
const { unlinkSync } = await import('node:fs')
unlinkSync(htmlPath)
console.log('✔ docs/report/informe.pdf generado')
