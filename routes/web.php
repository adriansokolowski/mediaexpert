<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// This project exposes a REST API only; the root just points at it.
Route::get('/', fn () => response()->json([
    'service' => 'appointment-scheduling',
    'docs' => 'See README.md',
    'endpoints' => [
        'GET /api/availability?date=YYYY-MM-DD',
        'POST /api/appointments',
        'GET /api/appointments',
        'GET /api/appointments/{id}',
        'PATCH /api/appointments/{id}',
        'DELETE /api/appointments/{id}',
        'GET /api/closing-days',
    ],
]));
