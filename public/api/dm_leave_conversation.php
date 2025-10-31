<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$current_user_id = $_SESSION['user']['user_id'];
$conversation_id = $_POST['conversation_id'] ?? 0;

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => '대화방 ID가 필요합니다.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 대화방 참가 여부 확인
    $stmt = $pdo->prepare("
        SELECT 1 FROM dm_participants
        WHERE conversation_id = ? 
        AND user_id = ?
        AND is_active = TRUE
    ");
    $stmt->execute([$conversation_id, $current_user_id]);
    
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '대화방에 참가하지 않았습니다.']);
        exit;
    }
    
    // 참가자 정보 업데이트 (나간 시점 기록 + 비활성화)
    $stmt = $pdo->prepare("
        UPDATE dm_participants 
        SET is_active = FALSE,
            left_at = NOW()
        WHERE conversation_id = ? 
        AND user_id = ?
    ");
    $stmt->execute([$conversation_id, $current_user_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '대화방을 나갔습니다.'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("DM Leave Conversation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '대화방 나가기에 실패했습니다.'
    ]);
}
?>