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
$conversation_id = $_GET['conversation_id'] ?? 0;

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => '대화방 ID가 필요합니다.']);
    exit;
}

try {
    // 대화방 참가 여부 및 나간 시점 확인
    $stmt = $pdo->prepare("
        SELECT left_at FROM dm_participants
        WHERE conversation_id = ? 
        AND user_id = ?
        AND is_active = TRUE
    ");
    $stmt->execute([$conversation_id, $current_user_id]);
    $participant = $stmt->fetch();
    
    if (!$participant) {
        echo json_encode(['success' => false, 'message' => '대화방에 참가하지 않았습니다.']);
        exit;
    }
    
    // 대화방 정보
    $stmt = $pdo->prepare("
        SELECT * FROM dm_conversations 
        WHERE conversation_id = ?
    ");
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch();
    
    // 참가자 정보
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.nickname, u.profile_img
        FROM dm_participants p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.conversation_id = ? AND p.is_active = TRUE
    ");
    $stmt->execute([$conversation_id]);
    $participants = $stmt->fetchAll();
    
    // 대화 상대 정보
    $conversation_name = $conversation['group_name'];
    $conversation_img = null;
    
    if (!$conversation['is_group']) {
        $other_user_found = false;
        
        foreach ($participants as $p) {
            if ($p['user_id'] != $current_user_id) {
                $other_user_found = true;
                $conversation_name = $p['nickname'];
                $conversation_img = $p['profile_img'];
                
                if (empty($conversation_img) || $conversation_img === 'assets/images/sample.png') {
                    $conversation_img = ASSETS_URL . '/images/sample.png';
                } else if (strpos($conversation_img, 'http') === 0) {
                    // 이미 전체 URL인 경우
                } else if (strpos($conversation_img, 'uploads/') === 0) {
                    $conversation_img = BASE_URL . '/' . $conversation_img;
                } else {
                    $conversation_img = UPLOADS_URL . '/profiles/' . basename($conversation_img);
                }
                break;
            }
        }
        
        // 자기 자신과의 대화인 경우
        if (!$other_user_found) {
            $conversation_name = $_SESSION['user']['nickname'] . ' (나)';
            $conversation_img = $_SESSION['user']['profile_img'];
            
            if (empty($conversation_img) || $conversation_img === 'assets/images/sample.png') {
                $conversation_img = ASSETS_URL . '/images/sample.png';
            } else if (strpos($conversation_img, 'http') === 0) {
                // 이미 전체 URL인 경우
            } else if (strpos($conversation_img, 'uploads/') === 0) {
                $conversation_img = BASE_URL . '/' . $conversation_img;
            } else {
                $conversation_img = UPLOADS_URL . '/profiles/' . basename($conversation_img);
            }
        }
    }
    
    // 메시지 가져오기 - left_at 이후의 메시지만 (재입장 후 메시지만)
    if ($participant['left_at']) {
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.username,
                u.nickname,
                u.profile_img
            FROM dm_messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            AND m.created_at > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id, $participant['left_at']]);
    } else {
        // 한 번도 나간 적 없으면 모든 메시지
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.username,
                u.nickname,
                u.profile_img
            FROM dm_messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
    }
    $messages = $stmt->fetchAll();
    
    // 메시지 프로필 이미지 URL 처리
    foreach ($messages as &$msg) {
        if (empty($msg['profile_img']) || $msg['profile_img'] === 'assets/images/sample.png') {
            $msg['profile_img'] = ASSETS_URL . '/images/sample.png';
        } else if (strpos($msg['profile_img'], 'http') === 0) {
            // 이미 전체 URL인 경우
        } else if (strpos($msg['profile_img'], 'uploads/') === 0) {
            $msg['profile_img'] = BASE_URL . '/' . $msg['profile_img'];
        } else {
            $msg['profile_img'] = UPLOADS_URL . '/profiles/' . basename($msg['profile_img']);
        }
    }
    
    // 읽음 처리
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO dm_read_status (message_id, user_id)
        SELECT m.message_id, ?
        FROM dm_messages m
        WHERE m.conversation_id = ?
        AND m.sender_id != ?
    ");
    $stmt->execute([$current_user_id, $conversation_id, $current_user_id]);
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation_id,
        'conversation_name' => $conversation_name,
        'conversation_img' => $conversation_img,
        'is_group' => (bool)$conversation['is_group'],
        'participant_count' => count($participants),
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log("DM Get Messages Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '메시지를 불러올 수 없습니다.'
    ]);
}
?>