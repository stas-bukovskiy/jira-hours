<?php

declare(strict_types= 1);
ini_set('display_errors', '0');

require __DIR__ . "/../vendor/autoload.php";

use JiraHours\JiraClient, JiraHours\JiraException;

function respond(int $status, array $body): never {
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($body, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond(405, ['error' =>  'GET allowed only']);
}


try {
    $config = require __DIR__ . "/../config.php";
    if (!\is_array($config)) {
        throw new \RuntimeException('bad configuration');
    }

    $client = new JiraClient($config['base_url'], $config['token'], $config['verify_tls']);
    $response = $client->get('/rest/api/2/myself');
    respond(200, ['name' => $response['name'], 'displayName' => $response['displayName']]);
} catch (JiraException $e) {
    respond($e->getCode(), ['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log((string) $e);
    respond(500, ['error'=> 'something went wrong']);
}