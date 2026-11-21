<?php
declare(strict_types=1);

namespace JiraHours;

final class Worklog
{
    public function __construct(
        public readonly string $issueKey,
        public readonly \DateTimeImmutable $started,
        public readonly int $seconds,
        public readonly ?string $comment,
    ) {
    }

    /**
     * Named constructor: one place that knows the shape of a Jira worklog.
     *
     * @param array<string, mixed> $raw one item of "worklogs" from /issue/{key}/worklog
     */
    public static function fromJira(array $raw, string $issueKey, \DateTimeZone $tz): self
    {
        $started = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.vO', (string) ($raw['started'] ?? ''));
        if ($started === false) {
            throw new JiraException("invalid worklog start time in {$issueKey}", 502);
        }

        return new self(
            $issueKey,
            $started->setTimezone($tz),
            (int) ($raw['timeSpentSeconds'] ?? 0),
            isset($raw['comment']) ? (string) $raw['comment'] : null,
        );
    }
}
