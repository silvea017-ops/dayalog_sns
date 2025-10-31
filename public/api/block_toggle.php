<?php
// public/api/block_toggle.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/block.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = $_POST['user_id'] ?? null;
    $current_user_id = $_SESSION['user']['user_id'];
    $action = $_POST['action'] ?? 'block'; // 'block' or 'unblock'
    
    if (!$target_user_id) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }
    
    if ($target_user_id == $current_user_id) {
        echo json_encode(['success' => false, 'message' => '자기 자신을 차단할 수 없습니다.']);
        exit;
    }
    
    try {
        if ($action === 'unblock') {
            $result = unblockUser($pdo, $current_user_id, $target_user_id);
        } else {
            $result = blockUser($pdo, $current_user_id, $target_user_id);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => '처리 중 오류가 발생했습니다.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '잘못된 요청 방식입니다.']);
}

exit;