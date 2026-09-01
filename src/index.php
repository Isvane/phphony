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
