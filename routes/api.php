<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::get('locations', [LocationController::class, 'index']);

Route::get('availability', AvailabilityController::class);

Route::get('appointments', [AppointmentController::class, 'index']);
Route::post('appointments', [AppointmentController::class, 'store']);
Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
Route::patch('appointments/{appointment}', [AppointmentController::class, 'reschedule']);
Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy']);
