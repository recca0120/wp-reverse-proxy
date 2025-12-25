<?php

// Simple echo server for testing HTTP clients
header('Content-Type: application/json');

$response = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => [],
    'body' => file_get_contents('php://input'),
];

// Collect request headers
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = str_replace('_', '-', substr($key, 5));
        $response['headers'][$headerName] = $value;
    }
}

// Handle special test endpoints
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/status/404':
        http_response_code(404);
        $response['status'] = 404;
        break;

    case '/status/500':
        http_response_code(500);
        $response['status'] = 500;
        break;

    case '/redirect':
        http_response_code(302);
        header('Location: http://localhost:'.$_SERVER['SERVER_PORT'].'/redirected');
        exit;

    case '/headers':
        header('X-Custom-Header: test-value');
        header('Set-Cookie: a=1');
        header('Set-Cookie: b=2', false);
        break;

    case '/delay':
        sleep(2);
        break;

    default:
        $response['status'] = 200;
}

echo json_encode($response, JSON_PRETTY_PRINT);
