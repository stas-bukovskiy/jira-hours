<?php

return [
    'base_url' => rtrim(env('JIRA_BASE_URL', ''), '/') ?: null,
    'token' => env('JIRA_TOKEN'),
    'timezone' => env('JIRA_TIMEZONE', 'Europe/Kyiv'),
    'verify_tls' => env('JIRA_VERIFY_TLS', true),
];