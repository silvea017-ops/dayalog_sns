<?php
// dayalog/public/api/comment_add.php
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

$post_id = $_POST['post_id'] ?? null;
$content = trim($_POST['content'] ?? '');
$parent_comment_id = !empty($_POST['parent_comment_id']) ? $_POST['parent_comment_id'] : null;
$user_id = $_SESSION['user']['user_id'];

if (!$post_id || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 입력값이 누락되었습니다.']);
    exit;
}

try {
    // 댓글 작성
    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, user_id, content, parent_comment_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$post_id, $user_id, $content, $parent_comment_id]);
    $comment_id = $pdo->lastInsertId();
    
    // 알림 생성
    if ($parent_comment_id) {
        // 대댓글인 경우: 원 댓글 작성자에게 알림
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
        $stmt->execute([$parent_comment_id]);
        $parent_comment = $stmt->fetch();
        
        if ($parent_comment && $parent_comment['user_id'] != $user_id) {
            createNotification($pdo, $parent_comment['user_id'], $user_id, 'reply', $comment_id);
        }
    } else {
        // 일반 댓글인 경우: 게시물 작성자에게 알림
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if ($post && $post['user_id'] != $user_id) {
            createNotification($pdo, $post['user_id'], $user_id, 'comment', $post_id);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => '댓글이 등록되었습니다.',
        'comment_id' => $comment_id
    ]);
    
} catch (Exception $e) {
    error_log("Comment creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '댓글 등록에 실패했습니다.']);
}
?>