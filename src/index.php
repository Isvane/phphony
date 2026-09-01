<?php

declare(strict_types = 1);

/**
 * @return array{method: string, path: string, headers: array<string, string>}|null
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
            'headers' => $headers
        ];
    }
}

$errorCode = null;
$errorMsg = null;

$socket = stream_socket_server('tcp://127.0.0.1:8000', $errorCode, $errorMsg);

if (!$socket) {
    echo "Server failed: {$errorMsg} ({$errorCode})\n";
    exit(1);
}

echo "Server running on http://127.0.0.1:8000\n";

while (true) {
    $conn = stream_socket_accept($socket);
    if ($conn === false) {
        break;
    }

    $buffer = '';

    while (!feof($conn)) {
        $chunk = fread($conn, 1024);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $buffer .= $chunk;

        if (str_contains($buffer, "\r\n\r\n")) {
            break;
        }
    }

    $parsed = parse_http($buffer);

    if (!is_array($parsed)) {
        fwrite($conn, "HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
        fclose($conn);
        continue;
    }

    /** @var array{method: string, path: string, headers: array<string, string>} $parsed */
    $method = $parsed['method'];
    $target = $parsed['path'];
    $headers = $parsed['headers'];

    $rawPath = parse_url($target, PHP_URL_PATH);
    $path = is_string($rawPath) ? $rawPath : '/';

    $rawQuery = parse_url($target, PHP_URL_QUERY);
    $queryParams = [];
    if (is_string($rawQuery)) {
        parse_str($rawQuery, $queryParams);
    }

    $contentLength = (int) ( $headers['content-length'] ?? 0 );

    $parts = explode("\r\n\r\n", $buffer, 2);
    $body = $parts[1] ?? '';

    while (strlen($body) < $contentLength && !feof($conn)) {
        $remaining = $contentLength - strlen($body);
        $chunk = fread($conn, $remaining);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $body .= $chunk;
    }

    echo "Method: {$method}\n";
    echo "Path: {$path}\n";
    echo 'Params: ' . json_encode($queryParams, JSON_THROW_ON_ERROR) . "\n";
    echo 'Headers: ' . json_encode($headers, JSON_THROW_ON_ERROR) . "\n";

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

    fwrite($conn, $response);
    fclose($conn);
}
