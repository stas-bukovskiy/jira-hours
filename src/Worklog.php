<?php
declare(strict_types=1);

namespace JiraHours;

final class Worklog
{
    public function __construct(
        public readonly string $issueKey,
        public readonly string $started,
        public readonly int $seconds,
        public readonly ?string $comment,
    ) {
    }
}