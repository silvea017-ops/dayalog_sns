<?php
// public/api/follow_toggle.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../functions/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $following_id = $_POST['user_id'] ?? null;
    $follower_id = $_SESSION['user']['user_id'];
    $redirect = $_POST['redirect'] ?? '../pages/user_profile.php?id=' . $following_id;
    
    if ($following_id && $following_id != $follower_id) {
        // 이미 팔로우/요청 중인지 확인
        $stmt = $pdo->prepare("SELECT follow_id, status FROM follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$follower_id, $following_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // 언팔로우 또는 요청 취소
            $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
            $stmt->execute([$follower_id, $following_id]);
            
            if ($existing['status'] === 'accepted') {
                $_SESSION['success_message'] = '팔로우를 취소했습니다.';
            } else {
                $_SESSION['success_message'] = '팔로우 요청을 취소했습니다.';
            }
        } else {
            // 상대방이 비공개 계정인지 확인
            $is_private = isPrivateAccount($pdo, $following_id);
            
            if ($is_private) {
                // 비공개 계정 - 팔로우 요청
                $stmt = $pdo->prepare("INSERT INTO follows (follower_id, following_id, status, requested_at) VALUES (?, ?, 'pending', NOW())");
                $stmt->execute([$follower_id, $following_id]);
                
                // 알림 생성
                createNotification($pdo, $following_id, $follower_id, 'follow_request');
                
                $_SESSION['success_message'] = '팔로우 요청을 보냈습니다.';
            } else {
                // 공개 계정 - 즉시 팔로우
                $stmt = $pdo->prepare("INSERT INTO follows (follower_id, following_id, status) VALUES (?, ?, 'accepted')");
                $stmt->execute([$follower_id, $following_id]);
                
                // 알림 생성 (공개 계정은 follow_accept 알림)
                createNotification($pdo, $following_id, $follower_id, 'follow_accept');
                
                $_SESSION['success_message'] = '팔로우했습니다.';
            }
        }
    }
}

header('Location: ' . $redirect);
exit;