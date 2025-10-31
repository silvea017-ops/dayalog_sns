<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

header('Content-Type: application/json');

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$current_user_id = $_SESSION['user']['user_id'];
$is_group = $_POST['is_group'] ?? '0';
$group_name = trim($_POST['group_name'] ?? '');
$user_ids = json_decode($_POST['user_ids'] ?? '[]', true);

if (empty($user_ids)) {
    echo json_encode(['success' => false, 'message' => '사용자를 선택해주세요.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 1:1 대화인 경우 기존 대화방 확인
    if ($is_group === '0' && count($user_ids) === 1) {
        $other_user_id = $user_ids[0];
        
        // 자기 자신과의 대화인 경우
        if ($other_user_id == $current_user_id) {
            // 자기 자신과의 모든 대화방 찾기
            $stmt = $pdo->prepare("
                SELECT DISTINCT c.conversation_id
                FROM dm_conversations c
                INNER JOIN dm_participants p1 ON c.conversation_id = p1.conversation_id
                WHERE c.is_group = FALSE
                AND p1.user_id = ?
                AND (
                    SELECT COUNT(DISTINCT user_id) 
                    FROM dm_participants 
                    WHERE conversation_id = c.conversation_id
                ) = 1
                LIMIT 1
            ");
            $stmt->execute([$current_user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // 기존 대화방 재활성화 + left_at 초기화
                $stmt = $pdo->prepare("
                    UPDATE dm_participants 
                    SET is_active = TRUE,
                        left_at = NULL
                    WHERE conversation_id = ? AND user_id = ?
                ");
                $stmt->execute([$existing['conversation_id'], $current_user_id]);
                
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'conversation_id' => $existing['conversation_id']
                ]);
                exit;
            }
        } else {
            // 다른 사람과의 1:1 대화방 찾기 (양방향으로 확인)
            $stmt = $pdo->prepare("
                SELECT DISTINCT c.conversation_id
                FROM dm_conversations c
                INNER JOIN dm_participants p1 ON c.conversation_id = p1.conversation_id
                INNER JOIN dm_participants p2 ON c.conversation_id = p2.conversation_id
                WHERE c.is_group = FALSE
                AND p1.user_id = ?
                AND p2.user_id = ?
                AND p1.user_id != p2.user_id
                AND (
                    SELECT COUNT(DISTINCT user_id) 
                    FROM dm_participants 
                    WHERE conversation_id = c.conversation_id
                ) = 2
                LIMIT 1
            ");
            $stmt->execute([$current_user_id, $other_user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // 기존 대화방의 양쪽 참가자 모두 재활성화
                $stmt = $pdo->prepare("
                    UPDATE dm_participants 
                    SET is_active = TRUE 
                    WHERE conversation_id = ? AND user_id IN (?, ?)
                ");
                $stmt->execute([$existing['conversation_id'], $current_user_id, $other_user_id]);
                
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'conversation_id' => $existing['conversation_id']
                ]);
                exit;
            }
        }
    }
    
    // 새 대화방 생성
    $stmt = $pdo->prepare("
        INSERT INTO dm_conversations (is_group, group_name)
        VALUES (?, ?)
    ");
    $stmt->execute([$is_group === '1' ? 1 : 0, $group_name]);
    $conversation_id = $pdo->lastInsertId();
    
    // 현재 사용자 추가
    $stmt = $pdo->prepare("
        INSERT INTO dm_participants (conversation_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$conversation_id, $current_user_id]);
    
    // 선택된 사용자들 추가 (자기 자신이 아닌 경우에만)
    foreach ($user_ids as $user_id) {
        if ($user_id != $current_user_id) {
            $stmt->execute([$conversation_id, $user_id]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("DM Create Conversation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '대화방 생성에 실패했습니다.'
    ]);
}
?>