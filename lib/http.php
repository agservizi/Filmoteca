<?php

declare(strict_types=1);

function http_json_response(array $payload, int $status = 200, array $headers = []): void
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Failed to encode response']);
        return;
    }

    $etag = 'W/"' . sha1($body) . '"';
    $defaultHeaders = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'public, max-age=300',
        'ETag' => $etag,
    ];

    $headers = array_merge($defaultHeaders, $headers);

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        return;
    }

    http_response_code($status);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo $body;
}

function http_origin_allowed(?string $origin = null, array $allowedOrigins = []): bool
{
    if ($origin === null) {
        return false;
    }
    if (empty($allowedOrigins)) {
        return false;
    }
    if (in_array('*', $allowedOrigins, true)) {
        return true;
    }
    return in_array($origin, $allowedOrigins, true);
}
