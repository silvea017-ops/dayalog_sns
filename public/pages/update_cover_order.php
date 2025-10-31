<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// JSON 입력 받기
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/db.php';
    
    $pdo->beginTransaction();
    
    foreach ($data as $item) {
        if (!isset($item['id']) || !isset($item['order'])) {
            continue;
        }
        
        $cover_id = intval($item['id']);
        $order = intval($item['order']);
        
        // 권한 확인
        $stmt = $pdo->prepare("SELECT user_id FROM cover_images WHERE cover_id = ?");
        $stmt->execute([$cover_id]);
        $cover = $stmt->fetch();
        
        if (!$cover || $cover['user_id'] != $_SESSION['user']['user_id']) {
            continue;
        }
        
        // 순서 업데이트
        $stmt = $pdo->prepare("UPDATE cover_images SET display_order = ? WHERE cover_id = ?");
        $stmt->execute([$order, $cover_id]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '순서가 저장되었습니다.']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
exit;
?>