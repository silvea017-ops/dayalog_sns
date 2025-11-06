<?php
// public/api/comment_add.php (트위터 스타일 - 완전판)
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
    $pdo->beginTransaction();
    
    // 답글 대상 사용자명 가져오기
    $reply_to_username = null;
    if ($parent_comment_id) {
        $stmt = $pdo->prepare("
            SELECT u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.user_id 
            WHERE c.comment_id = ?
        ");
        $stmt->execute([$parent_comment_id]);
        $parent = $stmt->fetch();
        if ($parent) {
            $reply_to_username = $parent['username'];
        }
    }
    
    // 댓글 작성
    $stmt = $pdo->prepare("
        INSERT INTO comments (post_id, user_id, content, parent_comment_id, reply_to_username, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$post_id, $user_id, $content, $parent_comment_id, $reply_to_username]);
    $comment_id = $pdo->lastInsertId();
    
    // 이미지 업로드 처리
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = dirname(__DIR__) . '/uploads/comments/';
        
        // 디렉토리 생성
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_count = count($_FILES['images']['name']);
        $max_files = 4; // 댓글은 최대 4개까지
        
        for ($i = 0; $i < min($file_count, $max_files); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['images']['tmp_name'][$i];
                $file_name = $_FILES['images']['name'][$i];
                $file_type = $_FILES['images']['type'][$i];
                
                // 파일 타입 확인
                $media_type = 'image';
                if (strpos($file_type, 'video/') === 0) {
                    $media_type = 'video';
                    $max_size = 50 * 1024 * 1024; // 50MB
                } else {
                    $max_size = 5 * 1024 * 1024; // 5MB
                }
                
                // 파일 크기 확인
                if ($_FILES['images']['size'][$i] > $max_size) {
                    continue;
                }
                
                // 파일명 생성
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'comment_' . $comment_id . '_' . time() . '_' . $i . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    // DB에 저장
                    $relative_path = 'uploads/comments/' . $new_filename;
                    $stmt = $pdo->prepare("
                        INSERT INTO comment_images (comment_id, image_path, media_type, image_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$comment_id, $relative_path, $media_type, $i]);
                }
            }
        }
    }
    
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
            createNotification($pdo, $post['user_id'], $user_id, 'comment', $comment_id);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '댓글이 등록되었습니다.',
        'comment_id' => $comment_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Comment creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '댓글 등록에 실패했습니다.']);
}