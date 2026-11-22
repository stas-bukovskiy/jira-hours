<?php

declare(strict_types=1);

namespace App\Jira;

final class Issue
{
    public function __construct(
        public readonly string $key,
        public readonly string $summary,
    ) {}
}
