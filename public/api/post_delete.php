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
        // 먼저 해당 게시물이 현재 사용자의 것인지 확인
        $stmt = $pdo->prepare("SELECT user_id, image_path FROM posts WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if ($post && $post['user_id'] === $_SESSION['user']['user_id']) {
            // 이미지 파일이 있다면 삭제
            if ($post['image_path'] && file_exists(__DIR__ . '/../' . $post['image_path'])) {
                unlink(__DIR__ . '/../' . $post['image_path']);
            }
            
            // 데이터베이스에서 게시물 삭제
            $stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = ?");
            $stmt->execute([$post_id]);
            
            // 성공 메시지
            $_SESSION['success_message'] = '게시물이 삭제되었습니다.';
        } else {
            // 권한 없음
            $_SESSION['error_message'] = '권한이 없습니다.';
        }
    }
}

// 이전 페이지로 리다이렉트
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/index.php'));
exit;