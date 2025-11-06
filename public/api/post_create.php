<?php
// public/api/post_create.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

$content = trim($_POST['content'] ?? '');
$user_id = $_SESSION['user']['user_id'];

// 내용이 비어있고 미디어도 없으면 오류
$has_media = isset($_FILES['images']) && !empty($_FILES['images']['name'][0]);

if (empty($content) && !$has_media) {
    $_SESSION['error_message'] = '내용 또는 미디어를 입력해주세요.';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/pages/index.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 게시물 생성 - media_type 기본값 설정
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, media_type) VALUES (?, ?, 'image')");
    $stmt->execute([$user_id, $content]);
    $post_id = $pdo->lastInsertId();
    
    // 다중 미디어 처리 (이미지 + 영상)
    if ($has_media) {
        $upload_dir = dirname(__DIR__) . '/uploads/posts/';
        
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception('업로드 폴더 생성 실패');
            }
            chmod($upload_dir, 0777);
        }
        
        if (!is_writable($upload_dir)) {
            chmod($upload_dir, 0777);
            if (!is_writable($upload_dir)) {
                throw new Exception('업로드 폴더 쓰기 권한 없음');
            }
        }
        
        // 이미지 + 영상 타입 허용
        $allowed_types = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'
        ];
        $max_size = 50 * 1024 * 1024; // 50MB (영상 고려)
        $max_media = 8; // 최대 8개
        
        $media_count = min(count($_FILES['images']['name']), $max_media);
        
        // post_images 테이블에 삽입 (media_type 컬럼 포함)
        $stmt = $pdo->prepare("INSERT INTO post_images (post_id, image_path, image_order, media_type) VALUES (?, ?, ?, ?)");
        
        $uploaded_count = 0;
        $has_video = false;
        
        for ($i = 0; $i < $media_count; $i++) {
            if (!isset($_FILES['images']['error'][$i]) || $_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $file_type = $_FILES['images']['type'][$i];
            $file_size = $_FILES['images']['size'][$i];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('지원하지 않는 미디어 형식입니다.');
            }
            
            if ($file_size > $max_size) {
                throw new Exception('파일 크기는 50MB를 초과할 수 없습니다.');
            }
            
            // 미디어 타입 판별
            $media_type = strpos($file_type, 'video') !== false ? 'video' : 'image';
            if ($media_type === 'video') {
                $has_video = true;
            }
            
            $extension = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
            $filename = 'post_' . $post_id . '_' . time() . '_' . $uploaded_count . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $filepath)) {
                $db_path = 'uploads/posts/' . $filename;
                // media_type 값을 명시적으로 전달
                $stmt->execute([$post_id, $db_path, $uploaded_count, $media_type]);
                $uploaded_count++;
            } else {
                error_log("Failed to upload media: " . $_FILES['images']['name'][$i]);
            }
        }
        
        // posts 테이블의 media_type 업데이트 (영상이 있으면 video로)
        if ($has_video) {
            $updateStmt = $pdo->prepare("UPDATE posts SET media_type = 'video' WHERE post_id = ?");
            $updateStmt->execute([$post_id]);
        }
        
        if ($uploaded_count === 0 && empty($content)) {
            throw new Exception('미디어 업로드에 실패했습니다.');
        }
    }
    
    $pdo->commit();
    
    // 알림 생성
    if (file_exists(FUNCTIONS_PATH . '/notifications.php')) {
        require_once FUNCTIONS_PATH . '/notifications.php';
        $stmt = $pdo->prepare("
            SELECT follower_id FROM follows 
            WHERE following_id = ? AND status = 'accepted'
        ");
        $stmt->execute([$user_id]);
        $followers = $stmt->fetchAll();
        
        foreach ($followers as $follower) {
            createNotification($pdo, $follower['follower_id'], $user_id, 'post', $post_id);
        }
    }
    
    $_SESSION['success_message'] = '게시물이 작성되었습니다.';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/pages/index.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Post create error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/pages/index.php');
}