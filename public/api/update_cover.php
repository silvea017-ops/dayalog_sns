<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cover_img = null;
    
    // 헤더 이미지 업로드 처리
    if (!empty($_FILES['cover_img']) && $_FILES['cover_img']['error'] === UPLOAD_ERR_OK) {
        $uploaddir = __DIR__ . '/../uploads/';
        $ext = pathinfo($_FILES['cover_img']['name'], PATHINFO_EXTENSION);
        $filename = 'cover_' . $_SESSION['user']['user_id'] . '_' . time() . '.' . $ext;
        $dest = $uploaddir . $filename;
        
        if (move_uploaded_file($_FILES['cover_img']['tmp_name'], $dest)) {
            $cover_img = 'uploads/' . $filename;
            
            // 기존 헤더 이미지 삭제 (기본 이미지가 아닌 경우)
            if ($_SESSION['user']['cover_img'] && 
                file_exists(__DIR__ . '/../' . $_SESSION['user']['cover_img'])) {
                unlink(__DIR__ . '/../' . $_SESSION['user']['cover_img']);
            }
            
            // 데이터베이스 업데이트
            $stmt = $pdo->prepare("UPDATE users SET cover_img = ? WHERE user_id = ?");
            $stmt->execute([$cover_img, $_SESSION['user']['user_id']]);
            
            // 세션 정보 업데이트
            $_SESSION['user']['cover_img'] = $cover_img;
            $_SESSION['success_message'] = '헤더 이미지가 변경되었습니다.';
        } else {
            $_SESSION['error_message'] = '헤더 이미지 업로드에 실패했습니다.';
        }
    }
}

// 프로필 페이지로 리다이렉트
header('Location: user_profile.php?id=' . $_SESSION['user']['user_id']);
exit;