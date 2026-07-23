<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Presentation\Controllers;

use App\Shared\Domain\Contracts\BusinessMetricsLookup;
use Illuminate\Http\JsonResponse;

/**
 * The on-demand counterpart to BroadcastBusinessMetricsCommand's 5-second
 * loop — the call a dashboard makes once on load, before the WS channel
 * takes over for live updates (dashboard.updated, private-dashboard).
 */
final class BusinessDashboardController
{
    public function __construct(private readonly BusinessMetricsLookup $metrics)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->metrics->current()]);
    }
}
