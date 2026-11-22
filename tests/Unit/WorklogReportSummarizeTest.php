<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jira\Issue;
use App\Jira\JiraException;
use App\Jira\Week;
use App\Jira\Worklog;
use App\Jira\WorklogReport;
use PHPUnit\Framework\TestCase;

final class WorklogReportSummarizeTest extends TestCase
{
    private \DateTimeZone $kyiv;

    protected function setUp(): void
    {
        $this->kyiv = new \DateTimeZone('Europe/Kyiv');
    }

    public function test_it_ignores_worklogs_outside_the_week(): void
    {
        $week = Week::fromInput('2026-11-18', $this->kyiv);
        $worklogs = [
            $this->worklog('A-1', '2026-11-15T10:00:00.000+0200', 3600),
            $this->worklog('A-1', '2026-11-23T10:00:00.000+0200', 3600),
            $this->worklog('A-1', '2026-11-22T23:30:00.000+0200', 600),
        ];

        $report = WorklogReport::summarize($week, [new Issue('A-1', 'x')], $worklogs);

        $this->assertSame(600, $report['weekTotalSeconds']);
        $this->assertSame(600, $report['days'][6]['totalSeconds']);
    }

    public function test_it_sorts_entries_by_time_and_issues_by_seconds(): void
    {
        $week = Week::fromInput('2026-11-18', $this->kyiv);
        $worklogs = [
            $this->worklog('A-1', '2026-11-18T15:00:00.000+0200', 600),
            $this->worklog('B-2', '2026-11-18T09:00:00.000+0200', 3600),
        ];

        $report = WorklogReport::summarize($week, [new Issue('A-1', 'a'), new Issue('B-2', 'b')], $worklogs);

        $this->assertSame(['B-2', 'A-1'], array_column($report['days'][2]['entries'], 'issueKey'));
        $this->assertSame(['B-2', 'A-1'], array_column($report['issues'], 'issueKey'));
        $this->assertNull($report['days'][2]['entries'][0]['comment']);
    }

    public function test_a_broken_start_time_is_a_bad_gateway(): void
    {
        $this->expectException(JiraException::class);
        $this->expectExceptionCode(502);

        Worklog::fromJira(['started' => 'yesterday', 'timeSpentSeconds' => 60], 'A-1', $this->kyiv);
    }

    private function worklog(string $issueKey, string $started, int $seconds): Worklog
    {
        return Worklog::fromJira(['started' => $started, 'timeSpentSeconds' => $seconds], $issueKey, $this->kyiv);
    }
}
