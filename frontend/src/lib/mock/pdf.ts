import type { DocumentKind, HisPreview } from '@/types'

// A deliberately plain, printable DEMO artifact; it is not a clinical document template.
function ascii(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\x20-\x7E\n]/g, ' ')
}

function wrap(value: string, width = 86): string[] {
  return ascii(value)
    .split('\n')
    .flatMap((paragraph) => {
      const result: string[] = []
      let remaining = paragraph
      while (remaining.length > width) {
        const space = remaining.lastIndexOf(' ', width)
        const end = space > 0 ? space : width
        result.push(remaining.slice(0, end))
        remaining = remaining.slice(end).trimStart()
      }
      result.push(remaining)
      return result
    })
}

export function createDemoPdf(preview: HisPreview, kind: DocumentKind): Blob {
  const { patient, encounter, lines, warnings } = preview
  const content = [
    kind === 'his' ? 'Vista previa HIS de demostracion' : 'Resumen de atencion de demostracion',
    '',
    `Paciente ficticio: ${patient.first_name} ${patient.last_name}`,
    `HC: ${patient.chart_number}`,
    `Fecha de atencion: ${encounter.occurred_at}`,
    `Tipo: ${encounter.type}`,
    `Peso (kg): ${encounter.weight_kg ?? 'Sin registrar'}`,
    `Talla (cm): ${encounter.height_cm ?? 'Sin registrar'}`,
    `Hemoglobina (g/dL): ${encounter.hb_g_dl ?? 'Sin registrar'}`,
    '',
    ...(kind === 'clinical-note' ? ['Notas:', encounter.clinical_note ?? 'Sin notas.', ''] : []),
    'Actividades confirmadas por la persona usuaria:',
    ...(lines.length
      ? lines.map(
          (line) =>
            `${line.code} | ${line.label} | Tipo: ${line.diagnosis_type ?? '-'} | LAB: ${line.lab ?? '-'}`,
        )
      : ['Ninguna actividad confirmada.']),
    '',
    'Advertencias:',
    ...warnings,
    '',
    'No acredita atencion, diagnosticos ni validacion de codigos MINSA.',
  ].flatMap((line) => wrap(line))
  const pageLines = 42
  const chunks = Array.from(
    { length: Math.max(1, Math.ceil(content.length / pageLines)) },
    (_, index) => content.slice(index * pageLines, (index + 1) * pageLines),
  )
  const objects: string[] = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  ]
  const pageIds: number[] = []
  for (const [index, chunk] of chunks.entries()) {
    const pageId = objects.length + 1
    const streamId = pageId + 1
    pageIds.push(pageId)
    const text = [
      'KAY CARE - DEMO - SIN VALIDEZ OFICIAL',
      `Documento ficticio | Pagina ${index + 1} de ${chunks.length}`,
      '',
      ...chunk,
    ]
    const stream = `BT /F1 10 Tf 48 790 Td 15 TL\n${text.map((line, lineIndex) => `${lineIndex ? 'T* ' : ''}(${line.replace(/([\\()])/g, '\\$1')}) Tj`).join('\n')}\nET`
    objects.push(
      `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ${streamId} 0 R >>`,
    )
    objects.push(`<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`)
  }
  objects[1] = `<< /Type /Pages /Kids [${pageIds.map((id) => `${id} 0 R`).join(' ')}] /Count ${pageIds.length} >>`
  let pdf = '%PDF-1.4\n'
  const offsets = [0]
  objects.forEach((object, index) => {
    offsets.push(pdf.length)
    pdf += `${index + 1} 0 obj\n${object}\nendobj\n`
  })
  const xrefOffset = pdf.length
  pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`
  pdf += offsets
    .slice(1)
    .map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`)
    .join('')
  pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`
  return new Blob([pdf], { type: 'application/pdf' })
}
