<?php

declare(strict_types=1);
ini_set('display_errors', '0');

require __DIR__ . "/../vendor/autoload.php";

use JiraHours\JiraClient, JiraHours\JiraException, JiraHours\Week, JiraHours\InvalidInputException, JiraHours\WorklogReport;

function respond(int $status, array $body): never
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($body, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, ['error' => 'GET allowed only']);
}


try {
    $config = require __DIR__ . "/../config.php";
    if (!\is_array($config)) {
        throw new \RuntimeException('bad configuration');
    }

    $start = $_GET['start'] ?? null;
    if ($start !== null && !is_string($start)) {
        throw new InvalidInputException('start query param isn\'t string');
    }


    $tz = new \DateTimeZone($config["timezone"]);
    $week = Week::fromInput($start, $tz);

    $client = new JiraClient($config['base_url'], $config['token'], $config['verify_tls']);
    $response = $client->get('/rest/api/2/myself');

    $reporter = new WorklogReport($client);
    $report = $reporter->findIssues($week);

    respond(200, [
        'name' => $response['name'],
        'displayName' => $response['displayName'],
        'weekStart' => $week->weekStart(),
        'weekEnd' => $week->weekEnd(),
        'report' => $report,
    ]);
} catch (JiraException $e) {
    respond($e->getCode(), ['error' => $e->getMessage()]);
} catch (InvalidInputException $e) {
    respond(400, ['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log((string) $e);
    respond(500, ['error' => 'something went wrong']);
}