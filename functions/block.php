<?php
// functions/block.php

/**
 * 사용자 차단 여부 확인
 */
function isBlocked($pdo, $blocker_id, $blocked_id) {
    try {
        $stmt = $pdo->prepare("SELECT block_id FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$blocker_id, $blocked_id]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 양방향 차단 여부 확인 (A가 B를 차단했거나 B가 A를 차단한 경우)
 */
function isBlockedEither($pdo, $user_id_1, $user_id_2) {
    try {
        $stmt = $pdo->prepare("
            SELECT block_id FROM blocks 
            WHERE (blocker_id = ? AND blocked_id = ?) 
            OR (blocker_id = ? AND blocked_id = ?)
        ");
        $stmt->execute([$user_id_1, $user_id_2, $user_id_2, $user_id_1]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 사용자 차단
 */
function blockUser($pdo, $blocker_id, $blocked_id) {
    try {
        $pdo->beginTransaction();
        
        // 차단 추가
        $stmt = $pdo->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->execute([$blocker_id, $blocked_id]);
        
        // 팔로우 관계 삭제 (양방향)
        $stmt = $pdo->prepare("
            DELETE FROM follows 
            WHERE (follower_id = ? AND following_id = ?) 
            OR (follower_id = ? AND following_id = ?)
        ");
        $stmt->execute([$blocker_id, $blocked_id, $blocked_id, $blocker_id]);
        
        // 관련 알림 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE (user_id = ? AND from_user_id = ?) 
            OR (user_id = ? AND from_user_id = ?)
        ");
        $stmt->execute([$blocker_id, $blocked_id, $blocked_id, $blocker_id]);
        
        $pdo->commit();
        return ['success' => true, 'message' => '사용자를 차단했습니다.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => '차단에 실패했습니다.'];
    }
}

/**
 * 사용자 차단 해제
 */
function unblockUser($pdo, $blocker_id, $blocked_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$blocker_id, $blocked_id]);
        return ['success' => true, 'message' => '차단을 해제했습니다.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '차단 해제에 실패했습니다.'];
    }
}

/**
 * 차단한 사용자 목록 조회
 */
function getBlockedUsers($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.username, u.nickname, u.profile_img, u.bio, b.created_at as blocked_at
            FROM blocks b
            JOIN users u ON b.blocked_id = u.user_id
            WHERE b.blocker_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 서로 팔로우 중인지 확인
 */
function isMutualFollow($pdo, $user_id_1, $user_id_2) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM follows f1
            INNER JOIN follows f2 ON f1.follower_id = f2.following_id AND f1.following_id = f2.follower_id
            WHERE f1.follower_id = ? AND f1.following_id = ?
            AND f1.status = 'accepted' AND f2.status = 'accepted'
        ");
        $stmt->execute([$user_id_1, $user_id_2]);
        return $stmt->fetch()['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}