<?php

declare(strict_types=1);

use FlowEngine\Application\Http\ReadOnlyApi;

require __DIR__ . '/../vendor/autoload.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
$staticFile = $documentRoot . DIRECTORY_SEPARATOR . ltrim($requestPath, '/');

// Let PHP built-in server handle existing static files.
if ($requestPath !== '/' && is_file($staticFile)) {
    return false;
}

$projectPath = getenv('FLOW_ENGINE_API_PROJECT') ?: getcwd();
$api = new ReadOnlyApi((string) $projectPath);

$rawBody = (string) file_get_contents('php://input');
$parsedBody = [];
if ($rawBody !== '') {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $parsedBody = $decoded;
        }
    } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
        parse_str($rawBody, $parsedBody);
        if (!is_array($parsedBody)) {
            $parsedBody = [];
        }
    }
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (!is_string($value)) {
        continue;
    }

    if (str_starts_with($key, 'HTTP_')) {
        $normalized = strtolower(str_replace('_', '-', substr($key, 5)));
        $headers[$normalized] = $value;
    }
}

$parsedBody['_rawBody'] = $rawBody;
$response = $api->handle(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    $requestPath,
    $_GET,
    $parsedBody,
    $headers
);

http_response_code($response['status']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
