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
        $path = $parts[1] ?? '/';
        $headers = [];

        foreach ($lines as $line) {
            $kv = explode(':', $line, 2);
            if (count($kv) === 2) {
                $headers[strtolower(trim($kv[0]))] = trim($kv[1] ?? '');
            }
        }

        return [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'offset' => $headerEnd + 4
        ];
    }
}

function get_response(string $resHeader, string $resBody, string $contentType): ?string {
    return $resHeader
    . "Content-Type: {$contentType}\r\n"
    . 'Content-Length: '
    . strlen($resBody)
    . "\r\n"
    . "Connection: close\r\n\r\n"
    . $resBody;
}

$server = new SocketServer('127.0.0.1:8000');

$server->on('connection', function (ConnectionInterface $connection) {
    echo 'New connection from: ' . (string) $connection->getRemoteAddress() . "\n";
    $buffer = '';

    $connection->on('data', function ($chunk) use ($connection, &$buffer) {
        $buffer .= (string) $chunk;

        if (!str_contains($buffer, "\r\n\r\n")) {
            return;
        }

        $parsed = parse_http($buffer);
        if ($parsed === null) {
            $connection->end("HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
            return;
        }

        /** @var array{method: string, path: string, headers: array<string, string>, offset: int} $parsed */
        $method = $parsed['method'];
        $target = $parsed['path'];
        $headers = $parsed['headers'];
        $offset = $parsed['offset'];

        $contentLength = (int) ( $headers['content-length'] ?? 0 );

        if (strlen($buffer) < ( $offset + $contentLength )) {
            return;
        }
        $body = substr($buffer, $offset, $contentLength);

        if (strlen($body) < $contentLength) {
            return;
        }

        $parsedBody = [];
        if (str_contains($headers['content-type'] ?? '', 'application/json')) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($body, true);
            $parsedBody = is_array($decoded) ? $decoded : [];
        }

        $rawPath = parse_url($target, PHP_URL_PATH);
        $path = is_string($rawPath) ? $rawPath : '/';

        $rawQuery = parse_url($target, PHP_URL_QUERY);
        $queryParams = [];
        if (is_string($rawQuery)) {
            parse_str($rawQuery, $queryParams);
        }

        echo "Method: {$method}\n";
        echo "Path: {$path}\n";
        echo 'Params: ' . json_encode($queryParams, JSON_THROW_ON_ERROR) . "\n";
        echo 'Headers: ' . json_encode($headers, JSON_THROW_ON_ERROR) . "\n";
        echo 'Body: ' . json_encode($parsedBody, JSON_THROW_ON_ERROR) . "\n";

        $mime = [
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
            $publicDir !== false
            && $targetFile !== false
            && str_starts_with($targetFile, $publicDir)
            && is_file($targetFile)
        ) {
            $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
            $contentType = $mime[$ext] ?? 'application/octet-stream';
            $content = file_get_contents($targetFile);

            if ($content === false) {
                $resHeader = "HTTP/1.1 500 Internal Server Error\r\n";
                $resBody = "Unable to read file\n";
                $contentType = 'text/plain; charset=utf-8';

                $response = get_response($resHeader, $resBody, $contentType);

                $connection->end($response);

                return;
            }

            $resHeader = "HTTP/1.1 200 OK\r\n";
            $resBody = $content;

            $response = get_response($resHeader, $resBody, $contentType);

            $connection->end($response);

            return;
        }

        [$resHeader, $resBody, $contentType] = match ($path) {
            '/about' => [
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

        $response = get_response($resHeader, $resBody, $contentType);

        $connection->end($response);
    });
});

echo "Server running on http://127.0.0.1:8000\n";
