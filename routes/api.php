<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\ClosingDayController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Middleware\EnsureAdminToken;
use Illuminate\Support\Facades\Route;

Route::get('locations', [LocationController::class, 'index']);

Route::get('availability', AvailabilityController::class);

Route::get('appointments', [AppointmentController::class, 'index']);
Route::post('appointments', [AppointmentController::class, 'store']);
Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
Route::patch('appointments/{appointment}', [AppointmentController::class, 'reschedule']);
Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);

Route::get('closing-days', [ClosingDayController::class, 'index']);

Route::middleware(EnsureAdminToken::class)->group(function (): void {
    Route::post('closing-days', [ClosingDayController::class, 'store']);
    Route::delete('closing-days/{closingDay}', [ClosingDayController::class, 'destroy']);
});
