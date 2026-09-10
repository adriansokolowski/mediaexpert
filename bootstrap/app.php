<?php

declare(strict_types=1);

use App\Domain\Scheduling\Exceptions\SchedulingException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The application only speaks JSON, including for 404s and 405s.
        $exceptions->shouldRenderJsonWhen(static fn (): bool => true);

        // Broken business rules carry their own status code and a stable
        // machine-readable code, so clients can branch on "slot_already_booked"
        // instead of parsing prose.
        $exceptions->render(static function (SchedulingException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode(),
            ], $e->httpStatus());
        });
    })->create();
