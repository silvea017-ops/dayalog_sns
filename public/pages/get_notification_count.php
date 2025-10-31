<?php
// public/pages/get_notification_count.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../functions/notifications.php';
    
    $user_id = $_SESSION['user']['user_id'];
    
    $unread_count = getUnreadNotificationCount($pdo, $user_id);
    $follow_request_count = getFollowRequestCount($pdo, $user_id);
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'follow_request_count' => $follow_request_count
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;