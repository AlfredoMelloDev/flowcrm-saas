<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Dashboard\BuildDashboardSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, BuildDashboardSummary $buildDashboardSummary): JsonResponse
    {
        return response()->json([
            'data' => $buildDashboardSummary->handle($request->user()),
        ]);
    }
}
