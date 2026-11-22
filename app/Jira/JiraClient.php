<?php

declare(strict_types=1);

namespace App\Jira;

use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class JiraClient
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $token,
        private readonly bool $verifyTls,
    ) {}

    public function get(string $path, array $query = []): array
    {
        try {
            $resp = Http::baseUrl($this->baseUrl)->
                withToken($this->token)->
                acceptJson()->
                timeout(self::TIMEOUT)->
                connectTimeout(self::TIMEOUT)->
                withOptions(['verify' => $this->verifyTls])->
                get($path, $query);
        } catch (ConnectionException $e) {
            $prev = $e->getPrevious();
            if ($prev instanceof ConnectTimeoutException || $prev instanceof NetworkTimeoutException || $prev instanceof ResponseTimeoutException) {
                throw new JiraException($e->getMessage(), 504, $e);
            }

            throw new JiraException($e->getMessage(), 502, $e);
        }

        $statusCode = $resp->status();
        if ($statusCode < 200 || $statusCode >= 300) {
            match (true) {
                \in_array($statusCode, [401, 403], true) => throw new JiraException('invalid Jira access token', 401),
                $statusCode >= 400 && $statusCode < 500 => throw new JiraException("Jira rejected the request ({$statusCode})", 502),
                default => throw new JiraException("Jira returned {$statusCode}", 502),
            };
        }

        $responseDecoded = $resp->json();
        if (! \is_array($responseDecoded)) {
            throw new JiraException('received invalid JSON', 502);
        }

        return $responseDecoded;
    }
}
