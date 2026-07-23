<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});

/**
 * Self-hosted API reference (Redoc, vendored — no CDN, same reasoning as
 * Reverb over Pusher/Ably in ADR-0011) reading docs/openapi.yaml directly,
 * so it never drifts from the file that's actually source of truth — no
 * generation step, no copy to keep in sync. See ADR-0020.
 */
Route::get('/docs', function () {
    return response()->file(resource_path('docs/index.html'));
});

Route::get('/docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
});
