<?php
// public/api/get_post_info.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__, 2) . '/config/paths.php';
    require_once CONFIG_PATH . '/db.php';
    
    if (!isset($_GET['post_id'])) {
        throw new Exception('게시물 ID가 없습니다.');
    }

    $post_id = (int)$_GET['post_id'];
    $current_user_id = $_SESSION['user']['user_id'] ?? null;
    
    // 게시물 정보
    $stmt = $pdo->prepare("
        SELECT p.*, u.nickname, u.username, u.profile_img
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        throw new Exception('게시물을 찾을 수 없습니다.');
    }
    
    // 좋아요 수
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $post['like_count'] = $stmt->fetch()['count'];
    
    // 댓글 수
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $post['comment_count'] = $stmt->fetch()['count'];
    
    // 현재 사용자가 좋아요 했는지
    $post['user_liked'] = false;
    if ($current_user_id) {
        $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$current_user_id, $post_id]);
        $post['user_liked'] = $stmt->fetch() ? true : false;
    }
    
    echo json_encode([
        'success' => true,
        'post' => $post
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;