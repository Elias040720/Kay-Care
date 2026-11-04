<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EncounterController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HisController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientEncounterController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kay Care API v1 (immutable contract — see docs/openapi.yaml)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureActiveUser::class, 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->whereUuid('patient')->name('patients.show');
        Route::patch('patients/{patient}', [PatientController::class, 'update'])->whereUuid('patient')->name('patients.update');
        Route::get('patients/{patient}/encounters', [PatientEncounterController::class, 'index'])->whereUuid('patient')->name('patients.encounters');

        Route::post('encounters', [EncounterController::class, 'store'])->name('encounters.store');

        Route::whereUuid('encounter')->prefix('encounters/{encounter}')->group(function () {
            Route::get('/', [EncounterController::class, 'show'])->name('encounters.show');
            Route::patch('/', [EncounterController::class, 'update'])->name('encounters.update');
            Route::get('his-suggestions', [HisController::class, 'suggestions'])->name('encounters.his-suggestions');
            Route::put('his-lines', [HisController::class, 'replaceLines'])->name('encounters.his-lines');
            Route::get('his-preview', [HisController::class, 'preview'])->name('encounters.his-preview');
            Route::get('documents/his.pdf', [DocumentController::class, 'his'])->name('encounters.documents.his');
            Route::get('documents/clinical-note.pdf', [DocumentController::class, 'clinicalNote'])->name('encounters.documents.clinical-note');
        });
    });
});

// Unknown API paths (any method) answer JSON 404, never HTML.
Route::match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], '{fallbackPlaceholder}', fn () => response()->json(['message' => 'Recurso no encontrado.'], 404))
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
