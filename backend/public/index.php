<?php

declare(strict_types=1);

// Temporary health-endpoint stub verifying the compose plumbing (T007).
// Replaced by the Symfony front controller when the skeleton lands (T010).
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/api/health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['title' => 'Not found', 'status' => 404], JSON_THROW_ON_ERROR);
