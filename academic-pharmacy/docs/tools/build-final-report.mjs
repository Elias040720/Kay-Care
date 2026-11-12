// Genera docs/report/KAY_CARE_INFORME_FINAL.pdf a partir del Markdown.
//   cd academic-pharmacy/docs/tools && npm install && node build-final-report.mjs [carpeta-previsualizacion]
// - Paged.js pagina el documento (portada, índice con números de página,
//   encabezado y pie numerado) dentro del Chromium de Playwright del frontend.
// - Si se indica una carpeta, guarda una imagen PNG por página para revisar
//   la maquetación (tablas cortadas, páginas vacías, diagramas ilegibles).
import { mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { marked } from 'marked'

const here = dirname(fileURLToPath(import.meta.url))
const reportDir = resolve(here, '../report')
const previewDir = process.argv[2] ? resolve(process.argv[2]) : null
const { chromium } = createRequire(resolve(here, '../../frontend/package.json'))('@playwright/test')
const pagedPath = resolve(here, 'node_modules/pagedjs/dist/paged.polyfill.js')

const slug = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/<[^>]+>/g, '').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')

let body = marked.parse(readFileSync(resolve(reportDir, 'KAY_CARE_INFORME_FINAL.md'), 'utf8'), { gfm: true })

// Encabezados con id e índice automático (solo nivel 2).
const toc = []
body = body.replace(/<h2>(.*?)<\/h2>/g, (_m, inner) => {
  const id = 'sec-' + slug(inner)
  toc.push({ id, text: inner })
  return `<h2 id="${id}">${inner}</h2>`
})
const tocHtml = `<nav class="toc"><h1 class="toc-title">Tabla de contenido</h1><ol>${toc
  .map((t) => `<li><a href="#${t.id}"><span>${t.text}</span></a></li>`)
  .join('')}</ol></nav>`
body = body.replace('<div class="toc-placeholder"></div>', tocHtml)

// Imágenes con texto alternativo «Figura N. …» → figura con leyenda.
body = body.replace(/<p><img src="([^"]+)" alt="(Figura [^"]+)"><\/p>/g, (_m, src, alt) => {
  const wide = /27-github|28-github|23-docker|24-terraform|25-pruebas|26-regresion/.test(src) ? ' class="evidencia-cli"' : ''
  const tall = /01-inicio|informe-a1|e-arquitectura|20-movil/.test(src) ? ' class="alta"' : ''
  const medium = /informe-a2|informe-b|informe-d/.test(src) ? ' class="media"' : ''
  return `<figure${wide || tall || medium}><img src="${src}" alt="${alt}"><figcaption>${alt}</figcaption></figure>`
})
// Párrafos «Tabla N.» → leyenda de tabla.
body = body.replace(/<p><strong>(Tabla \d+\.)<\/strong>([^<]*)<\/p>/g, '<p class="tabla-leyenda"><strong>$1</strong>$2</p>')
// Códigos de requisitos sin corte de línea (guion no separable U+2011).
body = body.replace(/\b(RNF|RF)-(\d{2})\b/g, '$1‑$2')
// Referencias en un contenedor propio (alineación izquierda por las URL largas).
body = body.replace(/(<h2 id="sec-26-referencias">[\s\S]*)$/, '<div class="referencias">$1</div>')
// El resumen en inglés se separa con reglas del inglés.
body = body.replace('<p><strong>Abstract.</strong>', '<p lang="en"><strong>Abstract.</strong>')

const css = `
@page {
  size: A4;
  margin: 24mm 20mm 22mm 22mm;
  @top-left { content: "Kay Care Farmacia — Informe final"; font: 8pt "Segoe UI", Arial, sans-serif; color: #59645F; }
  @top-right { content: "Servicios Cloud · UTP"; font: 8pt "Segoe UI", Arial, sans-serif; color: #59645F; }
  @bottom-center { content: counter(page) " / " counter(pages); font: 8pt "Segoe UI", Arial, sans-serif; color: #59645F; }
}
@page portada { margin: 0; @top-left { content: none } @top-right { content: none } @bottom-center { content: none } }
@page indice { @top-left { content: none } @top-right { content: none } }
html { font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 10.5pt; line-height: 1.55; color: #18201D; }
body { margin: 0; }
h1, h2, h3 { color: #0B6346; line-height: 1.25; break-after: avoid; }
h2 { font-size: 15pt; margin: 1.5em 0 0.6em; padding-bottom: 4px; border-bottom: 2px solid #A6E0C4; string-set: seccion content(text); }
h3 { font-size: 12pt; }
p { text-align: justify; hyphens: auto; orphans: 3; widows: 3; }
li { text-align: left; hyphens: auto; orphans: 2; widows: 2; }
td, th { hyphens: none; }
.referencias p { text-align: left; hyphens: none; overflow-wrap: anywhere; }
a { color: #0E7A55; text-decoration: none; }
strong { color: #0B3B2A; }
code { font-family: Consolas, "Cascadia Mono", monospace; font-size: 8.8pt; background: #ECF8F2; padding: 0 3px; border-radius: 3px; }
pre { background: #18201D; color: #E6F2EA; padding: 10px 12px; border-radius: 6px; font-size: 8.4pt; line-height: 1.45; white-space: pre-wrap; break-inside: avoid; }
pre code { background: none; color: inherit; padding: 0; }
table { width: 100%; border-collapse: collapse; margin: 0.6em 0 1em; font-size: 8.8pt; }
thead { display: table-header-group; }
tr { break-inside: avoid; }
th { background: #0E7A55; color: #fff; text-align: left; padding: 5px 7px; font-weight: 600; }
td { border-bottom: 1px solid #DFEBE2; padding: 4px 7px; vertical-align: top; }
tbody tr:nth-child(even) td { background: #F6FAF7; }
.tabla-leyenda { margin: 1em 0 0.2em; font-size: 9pt; color: #4E5A55; text-align: left; break-after: avoid; }
figure { margin: 0.8em 0 1.2em; text-align: center; break-inside: avoid; }
figure img { max-width: 100%; max-height: 120mm; object-fit: contain; border: 1px solid #DFEBE2; border-radius: 6px; }
figure.alta img { max-height: 215mm; }
figure.media img { max-height: 190mm; }
figure.evidencia-cli img { max-height: 105mm; }
figcaption { font-size: 8.8pt; color: #4E5A55; margin-top: 4px; font-style: italic; }
blockquote { border-left: 4px solid #14966A; background: #ECF8F2; margin: 0.8em 0; padding: 6px 12px; }

.portada { page: portada; height: 297mm; box-sizing: border-box; padding: 34mm 26mm 22mm; background: linear-gradient(160deg, #ECF8F2 0%, #FFFFFF 45%, #E3EEF8 100%); display: flex; flex-direction: column; align-items: center; text-align: center; break-after: page; }
.portada p { text-align: center; margin: 0; hyphens: none; }
.portada-logo { margin-bottom: 16mm; }
.portada-universidad { font-size: 17pt; font-weight: 700; letter-spacing: 0.06em; color: #0B3B2A; }
.portada-escuela { font-size: 12pt; margin-top: 3mm !important; color: #24486B; }
.portada-curso { font-size: 11.5pt; margin-top: 2mm !important; color: #4E5A55; }
.portada-titulo { margin-top: 22mm !important; font-size: 17pt; font-weight: 800; line-height: 1.3; color: #0B6346; }
.portada-proyecto { margin-top: 9mm !important; font-size: 26pt; font-weight: 800; color: #18201D; }
.portada-datos { margin: 18mm auto 0; width: 62%; font-size: 11pt; }
.portada-datos td { border-bottom: 1px solid #C1D8C8; padding: 6px 8px; background: transparent !important; text-align: left; }
.portada-datos td:first-child { font-weight: 700; color: #0B6346; width: 34%; }
.portada-aviso { margin-top: auto !important; font-size: 9pt; font-weight: 700; letter-spacing: 0.1em; color: #fff; background: #0E7A55; padding: 7px 14px; border-radius: 6px; }

.toc { page: indice; break-after: page; }
.toc-title { font-size: 18pt; margin: 0 0 8mm; color: #0B6346; }
.toc ol { list-style: none; padding: 0; margin: 0; }
.toc li { margin: 0 0 2.4mm; text-align: left; border-bottom: 1px dotted #C1D8C8; padding-bottom: 1mm; }
.toc a { color: #18201D; display: block; }
.toc a::after { content: target-counter(attr(href), page); float: right; color: #0B6346; font-weight: 600; }
`

const html = `<!doctype html><html lang="es"><head><meta charset="utf-8">
<base href="${pathToFileURL(reportDir + '/').href}">
<title>Kay Care Farmacia — Informe final</title>
<style>${css}</style>
<script>window.PagedConfig = { auto: true, after: () => { window.__pagedDone = true } };</script>
<script src="${pathToFileURL(pagedPath).href}"></script>
</head><body>${body}</body></html>`

const tmp = resolve(reportDir, '.informe-final.tmp.html')
writeFileSync(tmp, html)
const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1000, height: 1400 } })
await page.goto(pathToFileURL(tmp).href, { waitUntil: 'load' })
await page.waitForFunction(() => window.__pagedDone === true, null, { timeout: 180_000 })
const pages = await page.locator('.pagedjs_page').count()
await page.pdf({ path: resolve(reportDir, 'KAY_CARE_INFORME_FINAL.pdf'), preferCSSPageSize: true, printBackground: true })

if (previewDir) {
  mkdirSync(previewDir, { recursive: true })
  for (let i = 0; i < pages; i++) {
    await page.locator('.pagedjs_page').nth(i).screenshot({ path: resolve(previewDir, `pagina-${String(i + 1).padStart(2, '0')}.png`), scale: 'css' })
  }
}
await browser.close()
unlinkSync(tmp)
console.log(`✔ KAY_CARE_INFORME_FINAL.pdf generado (${pages} páginas)`)
