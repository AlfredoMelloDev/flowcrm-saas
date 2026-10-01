<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\BuildReportsSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportsIndexRequest;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function index(ReportsIndexRequest $request, BuildReportsSummary $buildReportsSummary): JsonResponse
    {
        return response()->json([
            'data' => $buildReportsSummary->handle(
                $request->user(),
                $request->validated('user_id'),
                $request->validated('date_from'),
                $request->validated('date_to'),
            ),
        ]);
    }
}
