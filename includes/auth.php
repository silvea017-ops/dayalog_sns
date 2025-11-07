<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    // 세션 쿠키 수명 설정: 30일 (자동 로그인 OFF 사용자용)
    ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60);
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
    session_start();
}

// 세션이 없는 경우 쿠키 확인 (자동 로그인 ON 사용자용)
if (!isset($_SESSION['user'])) {
    // Remember Me 쿠키 확인
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
        require_once dirname(__DIR__) . '/config/db.php';
        
        $user_id = $_COOKIE['remember_user'];
        $token = $_COOKIE['remember_token'];
        
        // 데이터베이스에서 토큰 확인
        $stmt = $pdo->prepare("
            SELECT user_id, username, nickname, email, profile_img, bio, 
                   is_private, is_admin, show_all_tab, created_at 
            FROM users 
            WHERE user_id = ? AND remember_token = ?
        ");
        $stmt->execute([$user_id, $token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // 자동 로그인 성공 - 세션 생성
            $_SESSION['user'] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'],
                'email' => $user['email'],
                'profile_img' => $user['profile_img'],
                'bio' => $user['bio'],
                'is_private' => $user['is_private'],
                'is_admin' => $user['is_admin'],
                'show_all_tab' => (int)($user['show_all_tab'] ?? 1),
                'created_at' => $user['created_at']
            ];
            
            // 자동 로그인 플래그 저장
            $_SESSION['auto_login'] = true;
            
            // 쿠키는 갱신하지 않음 (영구 유지)
            // 이미 설정된 쿠키를 그대로 사용
        } else {
            // 토큰이 유효하지 않으면 쿠키 삭제
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
            setcookie('remember_user', '', time() - 3600, '/', '', false, true);
        }
    }
}

// 여전히 세션이 없으면 로그인 페이지로 리다이렉트
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>