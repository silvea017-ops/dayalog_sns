<?php
// public/api/get_post_media.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    // public/api에서 config로 가는 경로
    require_once dirname(__DIR__, 2) . '/config/paths.php';
    require_once CONFIG_PATH . '/db.php';
    
    if (!isset($_GET['post_id'])) {
        throw new Exception('게시물 ID가 없습니다.');
    }

    $post_id = (int)$_GET['post_id'];
    
    if ($post_id <= 0) {
        throw new Exception('유효하지 않은 게시물 ID입니다.');
    }

    $stmt = $pdo->prepare("
        SELECT image_path, media_type, image_order
        FROM post_images 
        WHERE post_id = ? 
        ORDER BY image_order ASC
    ");
    
    $stmt->execute([$post_id]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'media' => $media,
        'count' => count($media)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;