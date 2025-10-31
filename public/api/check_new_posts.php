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

if ($current_user_id) {
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
} else {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.post_id > ? 
        AND u.is_private = 0
    ");
    $stmt->execute([$latest_id]);
}

$result = $stmt->fetch();
echo json_encode([
    'success' => true,
    'new_posts' => $result['count']
]);