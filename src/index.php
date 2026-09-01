<?php

declare(strict_types = 1);

$errorCode = null;
$errorMsg = null;

$socket = stream_socket_server(address: 'tcp://127.0.0.1:8000', error_code: $errorCode, error_message: $errorMsg);

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

    $line = fgets($conn);

    if ($line !== false) {
        $trimmed = trim($line);
        $parts = explode(' ', $trimmed, 3);

        if (count($parts) === 3) {
            [$method, $target, $version] = $parts;

            $path = parse_url($target, PHP_URL_PATH) ?? '/';
            $query = parse_url($target, PHP_URL_QUERY) ?? '';

            $queryParams = [];
            if ($query !== false) {
                parse_str($query, $queryParams);
            }

            echo "Method: {$method}\n";
            echo "Path: {$path}\n";
            echo 'Params: ' . json_encode($queryParams, JSON_THROW_ON_ERROR) . "\n";
        }
        echo 'Received: ' . $line;

        $body = 'Hello PHP';
        $response =
            "HTTP/1.1 200 OK\r\n"
            . "Content-Type: text/plain\r\n"
            . 'Content-Length: '
            . strlen($body)
            . "\r\n"
            . "Connection: close\r\n\r\n"
            . $body;

        fwrite($conn, $response);
    }

    fclose($conn);
}
