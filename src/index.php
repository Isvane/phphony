<?php

declare(strict_types = 1);

require './vendor/autoload.php';

use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;

/**
 * @return array{method: string, path: string, headers: array<string, string>, offset: int}|null
 */

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

        $resBody = '';
        $resHeader = "HTTP/1.1 200 OK\r\n";

        switch ($path) {
            case '/':
                $resBody = "Welcome to my first PHP project\n";
                break;
            case '/about':
                $resBody = "I'm Isvane, a 3rd year college student\n";
                break;
            default:
                $resHeader = "HTTP/1.1 404 Not Found\r\n";
                $resBody = "Page not found\n";
                break;
        }

        $response =
            $resHeader
            . "Content-Type: text/plain\r\n"
            . 'Content-Length: '
            . strlen($resBody)
            . "\r\n"
            . "Connection: close\r\n\r\n"
            . $resBody;

        $connection->end($response);
    });
});

echo "Server running on http://127.0.0.1:8000\n";
