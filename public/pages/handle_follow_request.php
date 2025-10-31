<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../functions/notifications.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $follow_id = $_POST['follow_id'] ?? null;
    $action = $_POST['action'] ?? null; // 'accept' or 'reject'
    
    if ($follow_id && in_array($action, ['accept', 'reject'])) {
        // 팔로우 요청 정보 확인
        $stmt = $pdo->prepare("
            SELECT * FROM follows 
            WHERE follow_id = ? AND following_id = ? AND status = 'pending'
        ");
        $stmt->execute([$follow_id, $_SESSION['user']['user_id']]);
        $follow_request = $stmt->fetch();
        
        if ($follow_request) {
            if ($action === 'accept') {
                // 요청 수락
                $stmt = $pdo->prepare("
                    UPDATE follows 
                    SET status = 'accepted', created_at = NOW() 
                    WHERE follow_id = ?
                ");
                $stmt->execute([$follow_id]);
                
                // 알림 생성
                createNotification(
                    $pdo, 
                    $follow_request['follower_id'], 
                    $_SESSION['user']['user_id'], 
                    'follow_accept'
                );
                
                $_SESSION['success_message'] = '팔로우 요청을 수락했습니다.';
            } else {
                // 요청 거절
                $stmt = $pdo->prepare("DELETE FROM follows WHERE follow_id = ?");
                $stmt->execute([$follow_id]);
                
                $_SESSION['success_message'] = '팔로우 요청을 거절했습니다.';
            }
        }
    }
}

header('Location: follow_requests.php');
exit;