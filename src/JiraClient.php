<?php
declare(strict_types=1);

namespace JiraHours;

final class JiraClient
{
    private const TIMEOUT = 15;

    public function __construct(
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $token,
        private readonly bool $verifyTls,
    ) {
    }


    public function get(string $path, array $query = []): array
    {
        $url = "{$this->baseUrl}{$path}";
        if ($query) {
            $url .= "?" . http_build_query($query);
        }


        $handle = curl_init();
        $curlOpts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->token}", "Accept: application/json"],
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ];
        if (!curl_setopt_array($handle, $curlOpts)) {
            throw new JiraException("curl option(s) are not set properly");
        }

        $response = curl_exec($handle);
        if ($response === false) {
            if (curl_errno($handle) === CURLE_OPERATION_TIMEDOUT) {
                throw new JiraException(curl_error($handle), 504);
            }
            throw new JiraException(curl_error($handle), 502);
        }

        $statusCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if ($statusCode < 200 || $statusCode >= 300) {
            match (true) {
                \in_array($statusCode, [401, 403], true) => throw new JiraException("invalid Jira access token", 401),
                400 <= $statusCode && $statusCode < 500 => throw new JiraException("Jira rejected the request ({$statusCode})", 502),
                default => throw new JiraException("Jira returned {$statusCode}", 502),
            };
        }

        try {
            $responseDecoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JiraException('received invalid JSON', 502, $e);
        }

        if (!\is_array($responseDecoded)) {
            throw new JiraException('received non-array response', 502);
        }

        return $responseDecoded;
    }

}