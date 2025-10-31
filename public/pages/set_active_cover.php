<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$cover_id = intval($_POST['cover_id'] ?? 0);

if (!$cover_id || !isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/db.php';
    
    // 이미지 소유권 확인
    $stmt = $pdo->prepare("SELECT user_id FROM cover_images WHERE cover_id = ?");
    $stmt->execute([$cover_id]);
    $cover = $stmt->fetch();

    if (!$cover) {
        echo json_encode(['success' => false, 'message' => '이미지를 찾을 수 없습니다.']);
        exit;
    }

    if ($cover['user_id'] != $_SESSION['user']['user_id']) {
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }

    // 모든 이미지 비활성화
    $stmt = $pdo->prepare("UPDATE cover_images SET is_active = 0 WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['user_id']]);

    // 선택한 이미지 활성화
    $stmt = $pdo->prepare("UPDATE cover_images SET is_active = 1 WHERE cover_id = ?");
    $stmt->execute([$cover_id]);

    echo json_encode(['success' => true, 'message' => '헤더 이미지가 변경되었습니다.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
exit;
?>