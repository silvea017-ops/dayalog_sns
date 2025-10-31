<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

header('Content-Type: application/json');

// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$current_user_id = $_SESSION['user']['user_id'];
$query = $_GET['q'] ?? '';

// 디버깅을 위한 로그
error_log("DM Search - User ID: {$current_user_id}, Query: {$query}");

if (strlen($query) < 1) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

try {
    // 사용자 검색 (자신 포함 - 자기 자신에게도 메시지 가능)
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.nickname,
            u.profile_img,
            u.dm_permission,
            u.is_private,
            EXISTS (
                SELECT 1 FROM follows 
                WHERE follower_id = ? 
                AND following_id = u.user_id 
                AND status = 'accepted'
            ) as is_following,
            EXISTS (
                SELECT 1 FROM follows 
                WHERE follower_id = u.user_id 
                AND following_id = ? 
                AND status = 'accepted'
            ) as is_follower
        FROM users u
        WHERE (u.username LIKE ? OR u.nickname LIKE ?)
        ORDER BY 
            CASE WHEN u.user_id = ? THEN 0 ELSE 1 END,
            u.nickname ASC
        LIMIT 20
    ");

    $searchTerm = "%{$query}%";
    $stmt->execute([
        $current_user_id, 
        $current_user_id, 
        $searchTerm, 
        $searchTerm,
        $current_user_id
    ]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("DM Search - Found users: " . count($users));

    // DM 권한 확인
    foreach ($users as &$user) {
        // 프로필 이미지 URL 처리
        if (empty($user['profile_img']) || $user['profile_img'] === 'assets/images/sample.png') {
            $user['profile_img'] = ASSETS_URL . '/images/sample.png';
        } else if (strpos($user['profile_img'], 'http') === 0) {
            // 이미 전체 URL인 경우
            $user['profile_img'] = $user['profile_img'];
        } else if (strpos($user['profile_img'], 'uploads/') === 0) {
            // uploads/로 시작하는 경우
            $user['profile_img'] = BASE_URL . '/' . $user['profile_img'];
        } else {
            // 파일명만 있는 경우
            $user['profile_img'] = UPLOADS_URL . '/profiles/' . basename($user['profile_img']);
        }
        
        // 자기 자신인 경우 항상 DM 가능
        if ($user['user_id'] == $current_user_id) {
            $user['can_send_dm'] = true;
            $user['is_self'] = true;
        } else {
            $user['is_self'] = false;
            // DM 권한 체크
            if ($user['dm_permission'] === 'everyone') {
                $user['can_send_dm'] = true;
            } elseif ($user['dm_permission'] === 'followers') {
                // 상대방이 나를 팔로우하고 있는지 확인
                $user['can_send_dm'] = (bool)$user['is_follower'];
            } else {
                $user['can_send_dm'] = false;
            }
        }
        
        error_log("User: {$user['username']}, Is Self: " . ($user['is_self'] ? 'YES' : 'NO') . ", Can Send: " . ($user['can_send_dm'] ? 'YES' : 'NO'));
        
        // 불필요한 필드 제거
        unset($user['is_following']);
        unset($user['is_follower']);
        unset($user['dm_permission']);
        unset($user['is_private']);
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'debug' => [
            'query' => $query,
            'user_count' => count($users),
            'current_user_id' => $current_user_id
        ]
    ]);
    
} catch (Exception $e) {
    error_log("DM Search Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '검색 중 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ]);
}
?>