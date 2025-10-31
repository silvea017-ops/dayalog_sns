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

try {
    // 읽지 않은 메시지가 있는 대화방 수 계산
    // 자기 자신과의 대화(참가자가 1명인 대화방)는 제외
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT m.conversation_id) as unread_count
        FROM dm_messages m
        INNER JOIN dm_participants p ON m.conversation_id = p.conversation_id
        INNER JOIN dm_conversations c ON m.conversation_id = c.conversation_id
        WHERE p.user_id = ?
        AND p.is_active = TRUE
        AND m.sender_id != ?
        AND m.message_id NOT IN (
            SELECT message_id 
            FROM dm_read_status 
            WHERE user_id = ?
        )
        AND (
            c.is_group = TRUE 
            OR (
                SELECT COUNT(*) 
                FROM dm_participants 
                WHERE conversation_id = m.conversation_id 
                AND is_active = TRUE
            ) > 1
        )
    ");
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$result['unread_count']
    ]);
    
} catch (Exception $e) {
    error_log("DM Unread Count Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '오류가 발생했습니다.',
        'unread_count' => 0
    ]);
}
?>