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
$message_text = trim($_POST['message_text'] ?? '');

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => '대화방 ID가 필요합니다.']);
    exit;
}

if (empty($message_text)) {
    echo json_encode(['success' => false, 'message' => '메시지를 입력해주세요.']);
    exit;
}

// 대화방 참가 여부 확인
$stmt = $pdo->prepare("
    SELECT 1 FROM dm_participants
    WHERE conversation_id = ? 
    AND user_id = ?
    AND is_active = TRUE
");
$stmt->execute([$conversation_id, $current_user_id]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '대화방에 참가하지 않았습니다.']);
    exit;
}

try {
    // 메시지 저장
    $stmt = $pdo->prepare("
        INSERT INTO dm_messages (conversation_id, sender_id, message_text)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $current_user_id, $message_text]);
    $message_id = $pdo->lastInsertId();
    
    // 본인은 자동으로 읽음 처리
    $stmt = $pdo->prepare("
        INSERT INTO dm_read_status (message_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$message_id, $current_user_id]);
    
    // 대화방 업데이트 시간 갱신
    $stmt = $pdo->prepare("
        UPDATE dm_conversations 
        SET updated_at = NOW()
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversation_id]);
    
    echo json_encode([
        'success' => true,
        'message_id' => $message_id
    ]);
    
} catch (Exception $e) {
    error_log("DM Send Message Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '메시지 전송에 실패했습니다.'
    ]);
}
?>