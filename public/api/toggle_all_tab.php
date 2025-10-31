<?php
// public/api/toggle_all_tab.php
session_start();

// 에러 출력 방지
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

try {
    // public/api에서 config/db.php로 접근
    require_once dirname(__DIR__, 2) . '/config/db.php';
    
    $user_id = $_SESSION['user']['user_id'];
    
    // 현재 설정 조회
    $stmt = $pdo->prepare("SELECT show_all_tab FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_setting = $stmt->fetch();
    
    if (!$current_setting) {
        echo json_encode(['success' => false, 'message' => '사용자를 찾을 수 없습니다.']);
        exit;
    }
    
    // 토글 (NULL인 경우 1로 처리)
    $current_value = isset($current_setting['show_all_tab']) ? $current_setting['show_all_tab'] : 1;
    $new_setting = $current_value ? 0 : 1;
    
    // 업데이트
    $stmt = $pdo->prepare("UPDATE users SET show_all_tab = ? WHERE user_id = ?");
    $stmt->execute([$new_setting, $user_id]);
    
    // 세션 업데이트
    $_SESSION['user']['show_all_tab'] = $new_setting;
    
    echo json_encode([
        'success' => true,
        'show_all_tab' => (bool)$new_setting,
        'message' => $new_setting ? '전체 탭이 표시됩니다.' : '전체 탭이 숨겨졌습니다.'
    ]);
    
} catch (Exception $e) {
    // 디버깅용 (배포 시 제거)
    echo json_encode([
        'success' => false,
        'message' => '설정 변경 중 오류가 발생했습니다.',
        'debug' => $e->getMessage()
    ]);
}
exit;
?>