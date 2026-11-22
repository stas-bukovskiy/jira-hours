<?php
declare(strict_types=1);

namespace App\Jira;

final class WorklogReport
{
    public function __construct(
        private readonly JiraClient $client,
        private readonly int $pageSize = 100,
    ) {
    }

    private function currentUserKey(): string
    {
        $response = $this->client->get('/rest/api/2/myself');
        $key = $response['key'] ?? '';
        if ($key === '') {
            throw new JiraException('got empty user key', 502);
        }
        return $key;
    }

    /**
     * @return array{
     *   weekStart: string,
     *   weekEnd: string,
     *   weekTotalSeconds: int,
     *   days: list<array{date: string, weekday: string, totalSeconds: int,
     *                    entries: list<array{issueKey: string, summary: string, seconds: int, comment: ?string}>}>,
     *   issues: list<array{issueKey: string, summary: string, seconds: int}>
     * }
     */
    public function build(Week $week): array
    {
        $issues = $this->findIssues($week);
        $worklogs = $this->findMyWorklogs($issues, $week);

        return self::summarize($week, $issues, $worklogs);
    }

    /**
     * Pure function: no HTTP, so it can be tested with fake data.
     *
     * @param list<Issue> $issues
     * @param list<Worklog> $worklogs
     * @return array{
     *   weekStart: string,
     *   weekEnd: string,
     *   weekTotalSeconds: int,
     *   days: list<array{date: string, weekday: string, totalSeconds: int,
     *                    entries: list<array{issueKey: string, summary: string, seconds: int, comment: ?string}>}>,
     *   issues: list<array{issueKey: string, summary: string, seconds: int}>
     * }
     */
    public static function summarize(Week $week, array $issues, array $worklogs): array
    {
        $summaries = [];
        foreach ($issues as $issue) {
            $summaries[$issue->key] = $issue->summary;
        }

        $days = [];
        foreach ($week->days() as $day) {
            $date = $day->format('Y-m-d');
            $days[$date] = [
                'date' => $date,
                'weekday' => $day->format('D'),
                'totalSeconds' => 0,
                'entries' => [],
            ];
        }


        usort($worklogs, fn(Worklog $a, Worklog $b) => $a->started <=> $b->started);

        $byIssue = [];
        $weekTotalSeconds = 0;
        foreach ($worklogs as $worklog) {
            $date = $worklog->started->format('Y-m-d');
            if (!isset($days[$date])) {
                continue;
            }

            $summary = $summaries[$worklog->issueKey] ?? '';

            $days[$date]['entries'][] = [
                'issueKey' => $worklog->issueKey,
                'summary' => $summary,
                'seconds' => $worklog->seconds,
                'comment' => $worklog->comment,
            ];
            $days[$date]['totalSeconds'] += $worklog->seconds;

            $byIssue[$worklog->issueKey] ??= [
                'issueKey' => $worklog->issueKey,
                'summary' => $summary,
                'seconds' => 0,
            ];
            $byIssue[$worklog->issueKey]['seconds'] += $worklog->seconds;

            $weekTotalSeconds += $worklog->seconds;
        }

        $issueTotals = array_values($byIssue);
        usort($issueTotals, fn(array $a, array $b) => $b['seconds'] <=> $a['seconds']);

        return [
            'weekStart' => $week->weekStart(),
            'weekEnd' => $week->weekEnd(),
            'weekTotalSeconds' => $weekTotalSeconds,
            'days' => array_values($days),
            'issues' => $issueTotals,
        ];
    }

    /**
     * @param list<Issue> $issues
     * @return list<Worklog>
     */
    public function findMyWorklogs(array $issues, Week $week): array
    {
        if (empty($issues)) {
            return [];
        }

        $currentUserKey = $this->currentUserKey();
        $results = [];
        foreach ($issues as $issue) {
            $worklogs = $this->getWorklogs($issue);
            $worklogs = array_values(array_filter($worklogs, fn($w) => ($w["author"]["key"] ?? '') === $currentUserKey));

            $mapped = array_map(fn($raw) => Worklog::fromJira($raw, $issue->key, $week->timeZone()), $worklogs);
            array_push($results, ...$mapped);
        }
        return $results;
    }

    private function getWorklogs(Issue $issue): array
    {
        $issueKey = \rawurlencode($issue->key);
        $worklogs = [];
        $startAt = 0;
        do {
            $response = $this->client->get("/rest/api/2/issue/{$issueKey}/worklog", ["startAt" => $startAt]);
            $worklog = $response["worklogs"] ?? [];
            if (count($worklog) === 0) {
                break;
            }

            array_push($worklogs, ...$worklog);
            $startAt += count($worklog);
        } while ($startAt < ($response['total'] ?? 0));
        return $worklogs;
    }



    /** @return list<Issue> */
    public function findIssues(Week $week): array
    {
        [$from, $to] = $week->searchRange();
        $queries = [
            "jql" => sprintf('worklogAuthor = currentUser() AND worklogDate >= "%s" AND worklogDate <= "%s"', $from, $to),
            "fields" => "summary",
            "startAt" => 0,
            "maxResults" => $this->pageSize,
        ];


        $results = [];
        do {
            $result = $this->client->get("/rest/api/2/search", $queries);
            $issues = $result["issues"] ?? [];
            if (count($issues) === 0) {
                break;
            }

            $mapped = array_map(fn($issue) => new Issue($issue["key"], $issue['fields']['summary'] ?? ''), $issues);
            array_push($results, ...$mapped);

            $queries['startAt'] += \count($issues);
        } while ($queries['startAt'] < ($result['total'] ?? 0));

        return $results;
    }
}