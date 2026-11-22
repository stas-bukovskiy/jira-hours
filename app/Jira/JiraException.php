<?php

declare(strict_types=1);

namespace App\Jira;

final class JiraException extends \RuntimeException
{
    public function __construct(string $message, int $status, ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}
