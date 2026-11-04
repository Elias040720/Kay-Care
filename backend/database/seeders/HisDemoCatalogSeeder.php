<?php

namespace Database\Seeders;

use App\Models\HisCatalogItem;
use App\Models\HisCatalogVersion;
use App\Models\HisRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DEMO HIS catalog for CRED.
 *
 * ALL codes below are FICTITIOUS ("DEMO-" prefix), are NOT official CIE-10,
 * CPT or MINSA HIS codes and have NOT been validated by MINSA or by a
 * professional reviewer. verification_status = draft, source = demo.
 * Age bands and sequences are illustrative only.
 */
class HisDemoCatalogSeeder extends Seeder
{
    public const VERSION_CODE = 'DEMO-CRED-2026.1';

    public function run(): void
    {
        DB::transaction(function () {
            $version = HisCatalogVersion::query()->updateOrCreate(
                ['code' => self::VERSION_CODE],
                [
                    'name' => 'Catálogo CRED demostrativo (NO oficial)',
                    'source' => 'demo',
                    'source_reference' => 'Creado para demostración de Kay Care. Sin procedencia oficial.',
                    'verification_status' => 'draft',
                    'valid_from' => '2015-01-01',
                    'valid_to' => null,
                    'is_active' => true,
                    'notes' => 'Reemplazar por el catálogo oficial mediante un proceso versionado y revisado (ver docs/his-rules.md).',
                ],
            );

            foreach ($this->definitions() as $definition) {
                $item = HisCatalogItem::query()->updateOrCreate(
                    ['catalog_version_id' => $version->id, 'code' => $definition['code']],
                    [
                        'label' => $definition['label'],
                        'category' => $definition['category'],
                        'allowed_diagnosis_types' => $definition['allowed'],
                        'diagnosis_type_required' => false,
                        'lab_mode' => $definition['lab_mode'],
                        'lab_options' => $definition['lab_options'] ?? null,
                        'verification_status' => 'draft',
                        'source' => 'demo',
                        'source_reference' => null,
                        'is_active' => true,
                        'notes' => 'Código DEMO no oficial.',
                    ],
                );

                foreach ($definition['rules'] as $rule) {
                    HisRule::query()->updateOrCreate(
                        ['catalog_version_id' => $version->id, 'rule_code' => $rule['rule_code']],
                        array_merge([
                            'catalog_item_id' => $item->id,
                            'min_age_days' => null,
                            'max_age_days' => null,
                            'min_age_months' => null,
                            'max_age_months' => null,
                            'min_age_years' => null,
                            'max_age_years' => null,
                            'sex' => null,
                            'condition' => null,
                            'diagnosis_type_suggested' => null,
                            'lab_strategy' => HisRule::LAB_NONE,
                            'lab_fixed' => null,
                            'max_sequence' => null,
                            'priority' => 100,
                            'verification_status' => 'draft',
                            'source' => 'demo',
                            'source_reference' => null,
                            'valid_from' => null,
                            'valid_to' => null,
                            'is_active' => true,
                        ], $rule),
                    );
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $controlTypes = ['D'];
        $openTypes = ['P', 'D', 'R'];

        return [
            [
                'code' => 'DEMO-CRED-RN', 'label' => 'Control CRED del recién nacido (DEMO)', 'category' => 'control',
                'allowed' => $controlTypes, 'lab_mode' => HisCatalogItem::LAB_CONTROL_NUMBER, 'lab_options' => ['max' => 4],
                'rules' => [[
                    'rule_code' => 'DEMO-R-CRED-RN', 'description' => 'Periodo neonatal (0 a 28 días).',
                    'min_age_days' => 0, 'max_age_days' => 28,
                    'diagnosis_type_suggested' => 'D', 'lab_strategy' => HisRule::LAB_CONTROL_NUMBER, 'max_sequence' => 4, 'priority' => 10,
                ]],
            ],
            [
                'code' => 'DEMO-CRED-M1', 'label' => 'Control CRED menor de 1 año (DEMO)', 'category' => 'control',
                'allowed' => $controlTypes, 'lab_mode' => HisCatalogItem::LAB_CONTROL_NUMBER, 'lab_options' => ['max' => 11],
                'rules' => [[
                    'rule_code' => 'DEMO-R-CRED-M1', 'description' => 'Menor de 1 año, desde los 29 días (meses cumplidos).',
                    'min_age_days' => 29, 'max_age_years' => 0,
                    'diagnosis_type_suggested' => 'D', 'lab_strategy' => HisRule::LAB_CONTROL_NUMBER, 'max_sequence' => 11, 'priority' => 10,
                ]],
            ],
            [
                'code' => 'DEMO-CRED-1A4', 'label' => 'Control CRED de 1 a 4 años (DEMO)', 'category' => 'control',
                'allowed' => $controlTypes, 'lab_mode' => HisCatalogItem::LAB_CONTROL_NUMBER, 'lab_options' => ['max' => 24],
                'rules' => [[
                    'rule_code' => 'DEMO-R-CRED-1A4', 'description' => 'Niño de 1 a 4 años cumplidos.',
                    'min_age_years' => 1, 'max_age_years' => 4,
                    'diagnosis_type_suggested' => 'D', 'lab_strategy' => HisRule::LAB_CONTROL_NUMBER, 'priority' => 10,
                ]],
            ],
            [
                'code' => 'DEMO-CRED-5A11', 'label' => 'Control CRED de 5 a 11 años (DEMO)', 'category' => 'control',
                'allowed' => $controlTypes, 'lab_mode' => HisCatalogItem::LAB_CONTROL_NUMBER, 'lab_options' => ['max' => 14],
                'rules' => [[
                    'rule_code' => 'DEMO-R-CRED-5A11', 'description' => 'Niño de 5 a 11 años cumplidos.',
                    'min_age_years' => 5, 'max_age_years' => 11,
                    'diagnosis_type_suggested' => 'D', 'lab_strategy' => HisRule::LAB_CONTROL_NUMBER, 'priority' => 10,
                ]],
            ],
            [
                'code' => 'DEMO-ANTRO', 'label' => 'Evaluación antropométrica (DEMO)', 'category' => 'evaluation',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NONE,
                'rules' => [[
                    'rule_code' => 'DEMO-R-ANTRO', 'description' => 'Se registró peso y/o talla en la atención.',
                    'max_age_years' => 11, 'condition' => HisRule::CONDITION_HAS_ANTHROPOMETRY, 'priority' => 20,
                ]],
            ],
            [
                'code' => 'DEMO-DESARROLLO', 'label' => 'Evaluación del desarrollo (DEMO)', 'category' => 'evaluation',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NONE,
                'rules' => [[
                    'rule_code' => 'DEMO-R-DESARROLLO', 'description' => 'Menor de 5 años.',
                    'max_age_years' => 4, 'priority' => 30,
                ]],
            ],
            [
                'code' => 'DEMO-HB', 'label' => 'Dosaje de hemoglobina (DEMO)', 'category' => 'lab',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NUMERIC, 'lab_options' => ['min' => 3, 'max' => 25],
                'rules' => [[
                    'rule_code' => 'DEMO-R-HB', 'description' => 'Se registró un valor de hemoglobina en la atención.',
                    'max_age_years' => 11, 'condition' => HisRule::CONDITION_HAS_HB,
                    'lab_strategy' => HisRule::LAB_MEASUREMENT_HB, 'priority' => 40,
                ]],
            ],
            [
                'code' => 'DEMO-CONS-LM', 'label' => 'Consejería en lactancia materna (DEMO)', 'category' => 'counseling',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NONE,
                'rules' => [[
                    'rule_code' => 'DEMO-R-CONS-LM', 'description' => 'De 0 a 5 meses cumplidos.',
                    'max_age_months' => 5, 'priority' => 50,
                ]],
            ],
            [
                'code' => 'DEMO-CONS-AC', 'label' => 'Consejería en alimentación complementaria (DEMO)', 'category' => 'counseling',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NONE,
                'rules' => [[
                    'rule_code' => 'DEMO-R-CONS-AC', 'description' => 'De 6 a 23 meses cumplidos.',
                    'min_age_months' => 6, 'max_age_months' => 23, 'priority' => 50,
                ]],
            ],
            [
                'code' => 'DEMO-TAMIZ-NEO', 'label' => 'Verificación de tamizaje neonatal (DEMO)', 'category' => 'screening',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NONE,
                'rules' => [[
                    'rule_code' => 'DEMO-R-TAMIZ-NEO', 'description' => 'Periodo neonatal: verificar si el tamizaje fue realizado.',
                    'min_age_days' => 0, 'max_age_days' => 28, 'priority' => 60,
                ]],
            ],
            [
                'code' => 'DEMO-AGUDEZA', 'label' => 'Evaluación de agudeza visual (DEMO)', 'category' => 'screening',
                'allowed' => $openTypes, 'lab_mode' => HisCatalogItem::LAB_NONE,
                'rules' => [[
                    'rule_code' => 'DEMO-R-AGUDEZA', 'description' => 'De 3 a 11 años cumplidos.',
                    'min_age_years' => 3, 'max_age_years' => 11, 'priority' => 60,
                ]],
            ],
        ];
    }
}
