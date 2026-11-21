<?php
declare(strict_types=1);

namespace JiraHours;

final class WorklogReport
{
    public function __construct(
        private readonly JiraClient $client,
        private readonly int $pageSize = 100,
    ) {
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