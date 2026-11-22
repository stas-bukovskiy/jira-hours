<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request, Illuminate\Http\JsonResponse;

final class WeekReportController extends Controller
{
    
    public function __invoke(Request $request) : JsonResponse
    {
        return response()->json(["start" => $request->query('start')]);
    }
}
