<?php

/*
|--------------------------------------------------------------------------
| Kay Care domain configuration
|--------------------------------------------------------------------------
*/

return [

    /*
    | "demo": fictitious data only, DEMO catalog, watermarked documents.
    | "clinical": reserved for a future deployment with real data. The MVP
    | refuses to seed demo data when this value is not "demo".
    */
    'mode' => env('KAYCARE_MODE', 'demo'),

    'api_version' => 'v1',

    /*
    | Timezone of the health facility. Used to decide "today", to reject
    | future encounters and to print document timestamps. Timestamps are
    | stored in UTC; the original offset of each encounter is preserved.
    */
    'timezone' => env('KAYCARE_TIMEZONE', 'America/Lima'),

    /* Tolerated clock skew (minutes) when rejecting future encounters. */
    'future_tolerance_minutes' => 5,

    'patients' => [
        'per_page' => 15,
        'search_max_length' => 100,
        'document_types' => ['DNI', 'CE', 'PASAPORTE', 'CNV', 'OTRO'],
    ],

    'encounters' => [
        'types' => ['CRED'],
        // CRED covers children from birth up to 11 years (inclusive).
        'cred_max_age_years' => 11,
        'ranges' => [
            'weight_kg' => ['min' => 0.3, 'max' => 150],
            'height_cm' => ['min' => 20, 'max' => 200],
            'hb_g_dl' => ['min' => 3, 'max' => 25],
        ],
        'clinical_note_max' => 5000,
        'history_limit' => 200,
    ],

    'his' => [
        'max_lines' => 20,
        'lab_max_length' => 10,
        'diagnosis_types' => ['P', 'D', 'R'],
    ],

    'documents' => [
        /*
        | Blade templates used to render each document. Swap these for
        | institution-approved templates once they are validated.
        */
        'templates' => [
            'his' => 'documents.demo.his',
            'clinical-note' => 'documents.demo.clinical-note',
        ],
        'watermark' => 'SOLO DEMOSTRACIÓN — NO VÁLIDO PARA USO CLÍNICO',
    ],

    /*
    | Demo accounts. Read ONLY by the demo seeder. Never hardcode values;
    | when a password is empty the corresponding account is not created.
    */
    'demo' => [
        'nurse' => [
            'name' => env('DEMO_NURSE_NAME', 'Enfermera Demo'),
            'email' => env('DEMO_NURSE_EMAIL'),
            'password' => env('DEMO_NURSE_PASSWORD'),
        ],
        'admin' => [
            'name' => env('DEMO_ADMIN_NAME', 'Administrador Demo'),
            'email' => env('DEMO_ADMIN_EMAIL'),
            'password' => env('DEMO_ADMIN_PASSWORD'),
        ],
    ],

];
