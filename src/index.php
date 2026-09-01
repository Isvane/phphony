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

    [$rawHeaders, $body] = explode("\r\n\r\n", $buffer, 2);

    $contentLen = 0;
    foreach (explode("\r\n", $rawHeaders) as $line) {
        if (stripos($line, 'Content-Length:') !== 0) {
            continue;
        }

        $contentLen = (int) trim(substr($line, 15));
        break;
    }

    while (strlen($body) < $contentLen && !feof($conn)) {
        $remainingBytes = $contentLen - strlen($body);
        $chunk = fread($conn, $remainingBytes);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $body .= $chunk;
    }

    $headerLines = explode("\r\n", $rawHeaders);
    $reqLines = $headerLines[0];

    $reqParts = explode(' ', $reqLines, 3);

    $method = null;
    $path = null;
    $queryParams = null;
    if (count($reqParts) === 3) {
        [$method, $target, $version] = $reqParts;

        $path = parse_url($target, PHP_URL_PATH) ?? '/';
        $query = parse_url($target, PHP_URL_QUERY) ?? '';

        $queryParams = [];
        if ($query !== false) {
            parse_str($query, $queryParams);
        }
    }

    if ($method === null) {
        fwrite($conn, "HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
        fclose($conn);
        continue;
    }

    echo "Method: {$method}\n";
    echo "Path: {$path}\n";
    echo 'Params: ' . json_encode($queryParams, JSON_THROW_ON_ERROR) . "\n";

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
            $resBody = 'Page not found\n';
            break;
    }

    echo "Received: {$body}\n";

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
