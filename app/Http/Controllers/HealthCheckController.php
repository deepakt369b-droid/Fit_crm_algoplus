<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Shallow health check for Coolify's zero-downtime deploy monitor.
 *
 * Deliberately does not touch the database — a slow/degraded DB
 * shouldn't cause Coolify to kill an otherwise-healthy container.
 * The deep check (including DB) is Laravel's own /up route.
 */
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }
}
