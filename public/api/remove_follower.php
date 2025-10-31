<?php
// public/api/remove_follower.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../functions/notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $follower_id = $_POST['follower_id'] ?? null;
    $current_user_id = $_SESSION['user']['user_id'];
    
    if (!$follower_id) {
        echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
        exit;
    }
    
    // 자기 자신은 삭제할 수 없음
    if ($follower_id == $current_user_id) {
        echo json_encode(['success' => false, 'message' => '자기 자신을 삭제할 수 없습니다.']);
        exit;
    }
    
    try {
        // 팔로우 관계 확인
        $stmt = $pdo->prepare("
            SELECT follow_id, status 
            FROM follows 
            WHERE follower_id = ? AND following_id = ?
        ");
        $stmt->execute([$follower_id, $current_user_id]);
        $follow = $stmt->fetch();
        
        if (!$follow) {
            echo json_encode(['success' => false, 'message' => '팔로우 관계가 존재하지 않습니다.']);
            exit;
        }
        
        // 팔로우 관계 삭제
        $stmt = $pdo->prepare("
            DELETE FROM follows 
            WHERE follower_id = ? AND following_id = ?
        ");
        $stmt->execute([$follower_id, $current_user_id]);
        
        // 관련 알림 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE (user_id = ? AND from_user_id = ?) 
            OR (user_id = ? AND from_user_id = ?)
        ");
        $stmt->execute([
            $current_user_id, $follower_id,
            $follower_id, $current_user_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => '팔로워가 삭제되었습니다.'
        ]);
        
    } catch (Exception $e) {
        error_log("팔로워 삭제 실패: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => '팔로워 삭제에 실패했습니다.'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '잘못된 요청 방식입니다.']);
}

exit;
?>