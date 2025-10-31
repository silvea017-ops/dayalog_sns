<?php
// public/api/like_toggle.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../functions/notifications.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$post_id = $_POST['post_id'] ?? null;
$user_id = $_SESSION['user']['user_id'];

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    // 게시물 작성자 확인
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => '게시물을 찾을 수 없습니다.']);
        exit;
    }
    
    $post_owner_id = $post['user_id'];
    
    // 이미 좋아요 했는지 확인
    $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // 좋아요 취소
        $stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        $liked = false;
    } else {
        // 좋아요 추가
        $stmt = $pdo->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$post_id, $user_id]);
        $liked = true;
        
        // 알림 생성 (자신의 게시물이 아닌 경우)
        if ($post_owner_id != $user_id) {
            createNotification($pdo, $post_owner_id, $user_id, 'like', $post_id);
        }
    }
    
    // 좋아요 개수 조회
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $like_count = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => $like_count
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;