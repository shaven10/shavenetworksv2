<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'data'    => notificationResponsePayload(),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = $input['action'] ?? '';

    if ($action === 'read') {
        $key = trim((string) ($input['key'] ?? ''));
        if ($key === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Notification key required.']);
            exit;
        }

        markNotificationsRead([$key]);
        echo json_encode([
            'success' => true,
            'data'    => notificationResponsePayload(),
        ]);
        exit;
    }

    if ($action === 'read_all') {
        markAllHeaderNotificationsRead();
        echo json_encode([
            'success' => true,
            'data'    => notificationResponsePayload(),
        ]);
        exit;
    }

    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Notification error.']);
}
