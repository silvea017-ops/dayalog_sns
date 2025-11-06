<?php
// public/api/post_delete.php
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 로그인 확인
if (!isset($_SESSION['user'])) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['id'] ?? null;
    
    if ($post_id) {
        try {
            // 트랜잭션 시작
            $pdo->beginTransaction();
            
            // 게시물 소유자 확인
            $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE post_id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch();
            
            if ($post && $post['user_id'] === $_SESSION['user']['user_id']) {
                // 1. 게시물 이미지 파일 삭제
                $stmt = $pdo->prepare("SELECT image_path FROM post_images WHERE post_id = ?");
                $stmt->execute([$post_id]);
                $images = $stmt->fetchAll();
                
                foreach ($images as $image) {
                    $file_path = __DIR__ . '/../' . $image['image_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // 2. 게시물 이미지 DB 삭제
                $stmt = $pdo->prepare("DELETE FROM post_images WHERE post_id = ?");
                $stmt->execute([$post_id]);
                
                // 3. 게시물에 대한 좋아요 알림 삭제
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE type = 'like' AND target_id = ?");
                $stmt->execute([$post_id]);
                
                // 4. 게시물의 댓글과 연관된 알림 삭제 (comment, reply)
                $stmt = $pdo->prepare("
                    DELETE n FROM notifications n
                    INNER JOIN comments c ON n.target_id = c.comment_id
                    WHERE n.type IN ('comment', 'reply') AND c.post_id = ?
                ");
                $stmt->execute([$post_id]);
                
                // 5. 좋아요 삭제
                $stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ?");
                $stmt->execute([$post_id]);
                
                // 6. 댓글 삭제 (CASCADE로 대댓글도 자동 삭제됨)
                $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
                $stmt->execute([$post_id]);
                
                // 7. 게시물 삭제
                $stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = ?");
                $stmt->execute([$post_id]);
                
                // 트랜잭션 커밋
                $pdo->commit();
                
                $_SESSION['success_message'] = '게시물이 삭제되었습니다.';
            } else {
                $pdo->rollBack();
                $_SESSION['error_message'] = '권한이 없습니다.';
            }
        } catch (Exception $e) {
            // 트랜잭션 롤백
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Post deletion error: " . $e->getMessage());
            $_SESSION['error_message'] = '게시물 삭제에 실패했습니다.';
        }
    }
}

// 이전 페이지로 리다이렉트
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/index.php'));
exit;