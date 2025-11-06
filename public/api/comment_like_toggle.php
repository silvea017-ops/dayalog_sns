<?php
// public/api/comment_like_toggle.php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/functions/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.']);
    exit;
}

$comment_id = $_POST['comment_id'] ?? null;
$user_id = $_SESSION['user']['user_id'];

if (!$comment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '댓글 ID가 필요합니다.']);
    exit;
}

try {
    // 이미 좋아요 했는지 확인
    $stmt = $pdo->prepare("SELECT like_id FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$user_id, $comment_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // 좋아요 취소
        $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE like_id = ?");
        $stmt->execute([$existing['like_id']]);
        $liked = false;
        
        // 알림 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE type = 'comment_like' 
            AND from_user_id = ? 
            AND target_id = ?
        ");
        $stmt->execute([$user_id, $comment_id]);
    } else {
        // 좋아요 추가
        $stmt = $pdo->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $comment_id]);
        $liked = true;
        
        // 댓글 작성자에게 알림 (자신의 댓글이 아닌 경우에만)
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        
        if ($comment && $comment['user_id'] != $user_id) {
            createNotification($pdo, $comment['user_id'], $user_id, 'comment_like', $comment_id);
        }
    }
    
    // 총 좋아요 수 조회
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    $like_count = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => $like_count
    ]);
    
} catch (Exception $e) {
    error_log("Comment like error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '좋아요 처리에 실패했습니다.']);
}