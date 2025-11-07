<?php
// public/pages/logout.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

session_start();

// Remember Me 토큰이 있는 경우 데이터베이스에서 삭제
if (isset($_SESSION['user']['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['user_id']]);
}

// 세션 파괴
session_destroy();

// Remember Me 쿠키 삭제
setcookie('remember_token', '', time() - 3600, '/', '', false, true);
setcookie('remember_user', '', time() - 3600, '/', '', false, true);

// 로그인 페이지로 리다이렉트
header('Location: ' . BASE_URL . '/pages/login.php');
exit;
?>