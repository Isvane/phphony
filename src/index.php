<?php

declare(strict_types = 1);

require './vendor/autoload.php';

use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;

/**
 * @return array{method: string, path: string, headers: array<string, string>, offset: int}|null
 */

// Fallback for when not using the Rust FFI.
if (!function_exists('parse_http')) {
    function parse_http(string $buffer): ?array
    {
        $headerEnd = strpos($buffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }

        $rawHeaders = substr($buffer, 0, $headerEnd);
        $lines = explode("\r\n", $rawHeaders);
        $requestLine = array_shift($lines);

        $parts = explode(' ', $requestLine, 3);
        if (count($parts) < 2) {
            return null;
        }

        $method = $parts[0];
        $target = $parts[1] ?? '/';
        /** @var array<string, string> $headers */
        $headers = [];

        foreach ($lines as $line) {
            $kv = explode(':', $line, 2);
            if (count($kv) === 2) {
                $headers[strtolower(trim($kv[0]))] = trim($kv[1] ?? '');
            }
        }

        $offset = $headerEnd + 4;
        $contentLength = (int) ( $headers['content-length'] ?? 0 );

        if (strlen($buffer) < ( $offset + $contentLength )) {
            return null;
        }

        $bodyStr = substr($buffer, $offset, $contentLength);
        $parsedBody = [];

        if (str_contains($headers['content-type'] ?? '', 'application/json')) {
            /** @var array|null $decoded */
            $decoded = json_decode($bodyStr, true);
            $parsedBody = is_array($decoded) ? $decoded : [];
        }

        $rawPath = parse_url($target, PHP_URL_PATH);
        $path = is_string($rawPath) ? $rawPath : '/';

        $rawQuery = parse_url($target, PHP_URL_QUERY);
        $queryParams = [];
        if (is_string($rawQuery)) {
            parse_str($rawQuery, $queryParams);
        }

        return [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'query' => $queryParams,
            'body' => $parsedBody
        ];
    }
}

function get_response(string $resHeader, string $resBody, string $contentType): ?string
{
    return (
        $resHeader
        . "Content-Type: {$contentType}\r\n"
        . 'Content-Length: '
        . strlen($resBody)
        . "\r\n"
        . "Connection: close\r\n\r\n"
        . $resBody
    );
}

function log_request(array $request): void
{
    /** @var array{method: string, path: string, headers: array<string, string>, query: string, body: string} $request */
    echo "Method: {$request['method']}\n";
    echo "Path: {$request['path']}\n";
    echo 'Params: ' . json_encode($request['query'], JSON_THROW_ON_ERROR) . "\n";
    echo 'Headers: ' . json_encode($request['headers'], JSON_THROW_ON_ERROR) . "\n";
    echo 'Body: ' . json_encode($request['body'], JSON_THROW_ON_ERROR) . "\n";
}

/**
 * @return array{0: string, 1: string, 2: string}|null
 */
function serve_static_file(string $path): ?array
{
    static $mime = [
        'css' => 'text/css',
        'html' => 'text/html',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain'
    ];

    $relativePath = $path === '/' ? '/index.html' : $path;
    $publicDir = realpath(__DIR__ . '/../public');
    $targetFile = realpath((string) $publicDir . '/' . ltrim($relativePath, '/'));

    if (
        $publicDir === false
        || $targetFile === false
        || !str_starts_with($targetFile, $publicDir)
        || !is_file($targetFile)
    ) {
        return null;
    }

    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
    $contentType = $mime[$ext] ?? 'application/octet-stream';
    $content = file_get_contents($targetFile);

    if ($content === false) {
        return ["HTTP/1.1 500 Internal Server Error\r\n", "Unable to read file\n", 'text/plain; charset=utf-8'];
    }

    return ["HTTP/1.1 200 OK\r\n", $content, $contentType];
}

/**
 * @return array{0: string, 1: string, 2: string}
 */
function dispatch_route(string $path): array
{
    return match ($path) {
        '/about', '/api' => [
            "HTTP/1.1 200 OK\r\n",
            "I'm Isvane, a 3rd year college student\n",
            'text/plain; charset=utf-8'
        ],
        default => [
            "HTTP/1.1 404 Not Found\r\n",
            "Page not found\n",
            'text/plain; charset=utf-8'
        ]
    };
}

function handle_request(array $request): string
{
    log_request($request);

    $path = (string) ( $request['path'] ?? '/' );
    [$header, $body, $contentType] = serve_static_file($path) ?? dispatch_route($path);

    return get_response($header, $body, $contentType) ?? '';
}

$server = new SocketServer('127.0.0.1:8000');

$server->on('connection', function (ConnectionInterface $connection) {
    echo 'New connection from: ' . (string) $connection->getRemoteAddress() . "\n";
    $buffer = '';

    $connection->on('data', function ($chunk) use ($connection, &$buffer) {
        $buffer .= (string) $chunk;

        if (( $request = parse_http($buffer) ) !== null) {
            $connection->end(handle_request($request));
        }
    });
});

echo "Server running on http://127.0.0.1:8000\n";
