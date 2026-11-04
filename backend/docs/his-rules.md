# Motor HIS/CRED: reglas, límites y procedencia

> ⚠️ **Todos los códigos actuales son DEMOSTRATIVOS** (prefijo `DEMO-`). No son códigos CIE-10, CPT
> ni HIS del MINSA. **No han sido validados por el MINSA ni por un profesional revisor.**
> Catálogo `DEMO-CRED-2026.1`, con `source = demo` y `verification_status = draft`.

## Regla fundamental

La edad y los datos registrados **solo generan SUGERENCIAS** de actividades que podrían aplicar.
El sistema:

- **no** registra procedimientos automáticamente (`GET his-suggestions` no escribe nada);
- **no** marca actividades como realizadas ni preseleccionadas (la sugerencia no tiene campo `selected`);
- **no** inventa códigos ni valores LAB;
- **no** convierte mediciones en diagnósticos (p. ej. no infiere anemia a partir de la hemoglobina);
- **no** asume controles anteriores si no hay historial registrado.

La enfermera **confirma** las actividades que efectivamente realizó con `PUT /encounters/{id}/his-lines`.
Solo esas líneas forman el registro.

## Cálculo de edad (`AgeCalculationService`)

- La edad se calcula a la **fecha de la atención** (la fecha local según el desplazamiento horario registrado), **nunca** con la edad actual.
- **Días**: días de calendario transcurridos desde el nacimiento (el día del nacimiento cuenta como 0).
- **Meses cumplidos**: un mes se cumple al llegar el mismo día del mes de nacimiento. Si ese día no existe en el mes de referencia (p. ej. nacido el 31), el mes se cumple el último día de ese mes. Un nacido el 29/02 cumple 12 meses el 28/02 del año siguiente.
- **Años cumplidos**: `floor(meses / 12)`.
- **Grupos**: `neonatal` (0–28 días), `under_1_year` (29 días a 11 meses), `years_1_4`, `years_5_11` y `out_of_cred_range` (12 años o más). Una atención CRED con 12 años o más en la fecha de la atención se rechaza con 422.

> Estas convenciones son técnicas y deben confirmarse con la norma técnica vigente antes de usar el sistema con datos reales.

## Estructura de datos

```
his_catalog_versions  code, source, source_reference, verification_status, valid_from, valid_to, is_active
  └─ his_catalog_items code, label, category, allowed_diagnosis_types[], diagnosis_type_required,
  │                    lab_mode (none|control_number|numeric|enum|free), lab_options{min,max,values}
  └─ his_rules         rule_code, description, catalog_item_id,
                       min/max_age_days, min/max_age_months, min/max_age_years (inclusivos, NULL = no evalúa),
                       sex, condition, diagnosis_type_suggested,
                       lab_strategy (none|control_number|measurement_hb|fixed), lab_fixed, max_sequence,
                       priority, source, verification_status, valid_from, valid_to, is_active
```

- **Vigencia**: para cada atención se usa el catálogo vigente en su fecha (`HisCatalogResolver`). Las líneas de un catálogo que no estaba vigente en esa fecha se rechazan (422).
- **Verificación**: una sugerencia o línea es `verified` solo si la regla, el ítem **y** la versión del catálogo están verificados. Con el catálogo demo siempre es `draft`.
- **Condiciones**: `has_anthropometry` (peso o talla registrados) y `has_hb` (hemoglobina registrada). Una condición desconocida **nunca** coincide (falla cerrada).
- **Una sugerencia por ítem**: si hay varias reglas para el mismo ítem, se usa la de menor `priority`.

## Valores LAB sugeridos

| Estrategia | Comportamiento |
|---|---|
| `none` | `lab_suggested = null` |
| `measurement_hb` | Se copia el valor de **hemoglobina registrado** en la atención. Si no hay valor, `null` |
| `control_number` | Ver más abajo. Si no hay historial, `null` |
| `fixed` | Valor fijo declarado en la regla, con procedencia |

### Número de control

Se calcula **solo a partir de líneas confirmadas en Kay Care** para el mismo ítem, el mismo paciente, el mismo rango de edad de la regla y fechas **anteriores** a la atención:

1. Si no hay controles previos registrados, `lab_suggested = null` y la justificación pide determinar el número con el carné o la historia clínica.
2. Si existen controles con número confirmado, se sugiere el siguiente: **último número confirmado + 1**.
3. Si existen controles sin número, se sugiere el siguiente: cantidad de controles + 1.
4. Si el resultado supera `max_sequence`, no se sugiere ningún número y se muestra una advertencia.

Siempre es una sugerencia. La enfermera verifica y confirma el número.

## Validación estructural (`HisValidationService`)

`PUT his-lines` rechaza (422, con errores por `lines.N.campo`):

- un `catalog_item_id` que no es UUID, que no existe o que no pertenece al catálogo vigente en la fecha de la atención;
- actividades duplicadas en la misma lista (además de la restricción única en BD);
- un `diagnosis_type` fuera de P/D/R o no permitido por el ítem;
- un `lab` que no cumple el modo del ítem (`none` no admite LAB, `control_number` exige un entero de 1 a `max`, `numeric` exige un número dentro del rango con un decimal como máximo, `enum` exige un valor de la lista y `free` admite solo caracteres alfanuméricos, `.` y `-`).

`GET his-preview` devuelve `validated = true` solo si no hay problemas bloqueantes: líneas presentes, LAB obligatorio presente (número de control o resultado), tipos permitidos, catálogo vigente y edad dentro del rango CRED.
`validated` indica **consistencia estructural**, **no validación oficial** (`validation_scope: "structural"`).
Las advertencias (catálogo DEMO no verificado, atención en borrador, actividad que no corresponde a la edad, paciente sin documento) se devuelven siempre.

## Catálogo DEMO actual

| Código DEMO | Actividad (ficticia) | Regla (edad en la atención) | LAB |
|---|---|---|---|
| DEMO-CRED-RN | Control CRED del recién nacido | 0–28 días | número de control (máx. 4) |
| DEMO-CRED-M1 | Control CRED menor de 1 año | ≥29 días y 0 años | número de control (máx. 11) |
| DEMO-CRED-1A4 | Control CRED 1 a 4 años | 1–4 años | número de control |
| DEMO-CRED-5A11 | Control CRED 5 a 11 años | 5–11 años | número de control |
| DEMO-ANTRO | Evaluación antropométrica | 0–11 años, con peso o talla | — |
| DEMO-DESARROLLO | Evaluación del desarrollo | 0–4 años | — |
| DEMO-HB | Dosaje de hemoglobina | 0–11 años, con Hb registrada | valor registrado |
| DEMO-CONS-LM | Consejería en lactancia materna | 0–5 meses | — |
| DEMO-CONS-AC | Consejería en alimentación complementaria | 6–23 meses | — |
| DEMO-TAMIZ-NEO | Verificación de tamizaje neonatal | 0–28 días | — |
| DEMO-AGUDEZA | Evaluación de agudeza visual | 3–11 años | — |

Los rangos y las secuencias son **ilustrativos** y no se basan en la norma técnica.
Fuente: `database/seeders/HisDemoCatalogSeeder.php`.

## Incorporación futura del catálogo oficial (proceso controlado)

1. Obtener el catálogo y la norma técnica vigente de una **fuente oficial** y registrar la referencia exacta (documento, versión, fecha y URL) en `source_reference`.
2. Crear una **nueva** `his_catalog_version` con un código propio (p. ej. `MINSA-HIS-AAAA.N`), `verification_status = draft`, `valid_from` / `valid_to` reales y `is_active = false`.
3. Cargar ítems y reglas mediante un seeder o comando de importación **versionado en Git** y revisado por pares. **No se editan a mano en producción.**
4. Revisión profesional: un profesional de salud responsable valida cada ítem y regla contra la norma. Se registra quién revisó y cuándo (`reviewed_by`, `reviewed_at`) y recién entonces se cambia `verification_status` a `verified`.
5. Ejecutar la suite de pruebas con casos de edad límite derivados de la norma.
6. Activar la versión (`is_active = true`) y cerrar la vigencia del catálogo anterior (`valid_to`). Las atenciones históricas siguen usando el catálogo vigente en su fecha.
7. Desactivar el catálogo DEMO en cualquier entorno con datos reales (`KAYCARE_MODE` distinto de `demo`).

No se implementa extracción clínica por IA ni envío de datos a modelos o servicios externos.
