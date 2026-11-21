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

    private function currentUserKey(): string
    {
        $response = $this->client->get('/rest/api/2/myself');
        $key = $response['key'] ?? '';
        if ($key === '') {
            throw new JiraException('got empty user key', 502);
        }
        return $key;
    }

    /** @param list<Issue> $issues
     *  @return list<Worklog> */
    public function findMyWorklogs(array $issues): array
    {
        if (empty($issues)) {
            return [];
        }

        $currentUserKey = $this->currentUserKey();
        $results = [];
        foreach ($issues as $issue) {
            $worklogs = $this->getWorklogs($issue);
            $worklogs = array_values(array_filter($worklogs, fn($w) => ($w["author"]["key"] ?? '') === $currentUserKey));
            $mapped = array_map(fn($worklog) => new Worklog(
                $issue->key,
                $worklog["started"],
                (int) ($worklog["timeSpentSeconds"] ?? 0),
                $worklog["comment"] ?? null,
            ), $worklogs);
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