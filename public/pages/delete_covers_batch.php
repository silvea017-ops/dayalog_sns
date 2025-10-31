<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$cover_ids = $_POST['cover_ids'] ?? '';

if (!$cover_ids || !isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/db.php';
    
    $ids = array_map('intval', explode(',', $cover_ids));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // 이미지 정보 조회
    $stmt = $pdo->prepare("SELECT cover_id, user_id, image_path, is_active FROM cover_images WHERE cover_id IN ($placeholders)");
    $stmt->execute($ids);
    $covers = $stmt->fetchAll();
    
    $deleted_count = 0;
    $had_active = false;
    
    foreach ($covers as $cover) {
        // 권한 확인
        if ($cover['user_id'] != $_SESSION['user']['user_id']) {
            continue;
        }
        
        // 파일 삭제
        $file_path = __DIR__ . '/../' . $cover['image_path'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        
        // DB에서 삭제
        $stmt = $pdo->prepare("DELETE FROM cover_images WHERE cover_id = ?");
        $stmt->execute([$cover['cover_id']]);
        
        $deleted_count++;
        if ($cover['is_active']) {
            $had_active = true;
        }
    }
    
    // 활성화된 이미지가 삭제되었다면 다음 이미지 활성화
    if ($had_active) {
        $stmt = $pdo->prepare("SELECT cover_id FROM cover_images WHERE user_id = ? ORDER BY display_order ASC, created_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user']['user_id']]);
        $next_cover = $stmt->fetch();
        
        if ($next_cover) {
            $stmt = $pdo->prepare("UPDATE cover_images SET is_active = 1 WHERE cover_id = ?");
            $stmt->execute([$next_cover['cover_id']]);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "{$deleted_count}개의 이미지가 삭제되었습니다."
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
exit;
?>