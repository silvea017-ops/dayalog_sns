<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$dm_permission = $_POST['dm_permission'] ?? 'everyone';

// 유효한 값인지 확인
if (!in_array($dm_permission, ['everyone', 'followers'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 설정값입니다.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET dm_permission = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$dm_permission, $user_id]);
    
    // 세션 업데이트
    $_SESSION['user']['dm_permission'] = $dm_permission;
    
    echo json_encode([
        'success' => true,
        'message' => 'DM 수신 설정이 변경되었습니다.'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '설정 변경에 실패했습니다.'
    ]);
}