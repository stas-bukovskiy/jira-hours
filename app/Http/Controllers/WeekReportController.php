<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jira\InvalidInputException;
use App\Jira\Week;
use App\Jira\WorklogReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WeekReportController extends Controller
{
    public function __invoke(Request $request, WorklogReport $report): JsonResponse
    {
        $start = $request->query('start');
        if ($start !== null && ! is_string($start)) {
            throw new InvalidInputException('start must be a date in YYYY-MM-DD format');
        }

        $week = Week::fromInput($start, new \DateTimeZone(config('jira.timezone')));

        return response()->json([
            ...$report->build($week),
            'jiraBaseUrl' => config('jira.base_url'),
        ]);
    }
}
