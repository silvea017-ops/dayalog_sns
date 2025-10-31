<?php
/**
 * Dayalog 메인 진입점
 * 최초 설치가 안 되어있으면 install.php로 리다이렉트
 */

// 설치 완료 확인 파일
$install_lock = __DIR__ . '/config/.installed';

// 설치가 완료되지 않았으면 설치 페이지로
if (!file_exists($install_lock)) {
    // install.php가 존재하는지 확인
    if (file_exists(__DIR__ . '/install.php')) {
        header("Location: install.php");
        exit;
    }
}

// 설치 완료 상태면 실제 메인 페이지로
header("Location: public/pages");
exit;
?>