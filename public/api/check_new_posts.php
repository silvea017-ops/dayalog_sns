<?php
// public/api/check_new_posts.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['latest_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$latest_id = (int)$_GET['latest_id'];
$current_user_id = $_SESSION['user']['user_id'] ?? null;
$is_following_feed = isset($_GET['following']) && $_GET['following'] == '1';

if (!$current_user_id) {
    // 비로그인 사용자는 공개 게시물만
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.post_id > ? 
        AND u.is_private = 0
    ");
    $stmt->execute([$latest_id]);
} elseif ($is_following_feed) {
    // 팔로잉 피드: 내 게시물 + 팔로우 중인 사용자의 게시물만 (차단된 사용자 제외)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.post_id > ? 
        AND (
            p.user_id = ? 
            OR EXISTS (
                SELECT 1 FROM follows f 
                WHERE f.follower_id = ? 
                AND f.following_id = p.user_id 
                AND f.status = 'accepted'
            )
        )
        AND p.user_id NOT IN (
            SELECT blocked_id FROM blocks WHERE blocker_id = ?
            UNION
            SELECT blocker_id FROM blocks WHERE blocked_id = ?
        )
    ");
    $stmt->execute([$latest_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
} else {
    // 전체 피드: 내 게시물 + 공개 게시물 + 팔로우 중인 비공개 계정 게시물
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.post_id > ? 
        AND (
            p.user_id = ? 
            OR u.is_private = 0 
            OR EXISTS (
                SELECT 1 FROM follows f 
                WHERE f.follower_id = ? 
                AND f.following_id = p.user_id 
                AND f.status = 'accepted'
            )
        )
    ");
    $stmt->execute([$latest_id, $current_user_id, $current_user_id]);
}

$result = $stmt->fetch();
echo json_encode([
    'success' => true,
    'new_posts' => $result['count']
]);