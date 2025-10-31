<?php
// public/api/comment_delete.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = $_POST['comment_id'] ?? null;
    $user_id = $_SESSION['user']['user_id'];
    
    if ($comment_id) {
        // 댓글 작성자 확인
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        
        if ($comment && $comment['user_id'] === $user_id) {
            // 댓글 삭제 (대댓글도 CASCADE로 자동 삭제됨)
            $stmt = $pdo->prepare("DELETE FROM comments WHERE comment_id = ?");
            $stmt->execute([$comment_id]);
            $_SESSION['success_message'] = '댓글이 삭제되었습니다.';
        } else {
            $_SESSION['error_message'] = '댓글을 삭제할 권한이 없습니다.';
        }
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? '../pages/index.php';
header('Location: ' . $referer);
exit;