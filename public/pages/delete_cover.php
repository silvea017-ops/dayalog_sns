<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 입력 검증
$cover_id = intval($_POST['cover_id'] ?? 0);

if (!$cover_id || !isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    // 경로 수정: profile.php와 같은 디렉토리 기준
    require_once __DIR__ . '/../../config/db.php';
    
    // 이미지 정보 조회
    $stmt = $pdo->prepare("SELECT user_id, image_path, is_active FROM cover_images WHERE cover_id = ?");
    $stmt->execute([$cover_id]);
    $cover = $stmt->fetch();

    if (!$cover) {
        echo json_encode(['success' => false, 'message' => '이미지를 찾을 수 없습니다.']);
        exit;
    }

    // 권한 확인
    if ($cover['user_id'] != $_SESSION['user']['user_id']) {
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }

    // 파일 삭제
    $file_path = __DIR__ . '/../' . $cover['image_path'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }

    // DB에서 삭제
    $stmt = $pdo->prepare("DELETE FROM cover_images WHERE cover_id = ?");
    $stmt->execute([$cover_id]);

    // 활성화된 이미지였다면 다음 이미지를 활성화
    if ($cover['is_active']) {
        $stmt = $pdo->prepare("SELECT cover_id FROM cover_images WHERE user_id = ? ORDER BY display_order ASC, created_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user']['user_id']]);
        $next_cover = $stmt->fetch();

        if ($next_cover) {
            $stmt = $pdo->prepare("UPDATE cover_images SET is_active = 1 WHERE cover_id = ?");
            $stmt->execute([$next_cover['cover_id']]);
        }
    }

    echo json_encode(['success' => true, 'message' => '헤더 이미지가 삭제되었습니다.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '오류: ' . $e->getMessage()]);
}
exit;
?>