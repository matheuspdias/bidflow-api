<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Presentation\Controllers;

use App\Modules\Dashboard\Infrastructure\ReadModels\TechnicalMetrics;
use Illuminate\Http\JsonResponse;

final class TechnicalDashboardController
{
    public function __construct(private readonly TechnicalMetrics $metrics)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->metrics->current()]);
    }
}
