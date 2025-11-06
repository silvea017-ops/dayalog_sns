<?php
// dayalog/functions/notifications.php

/**
 * 알림 생성 함수 (중복 방지 개선)
 */
function createNotification($pdo, $user_id, $from_user_id, $type, $target_id = null) {
    // 자신에게는 알림을 보내지 않음
    if ($user_id == $from_user_id) {
        return;
    }
    
    try {
        // 중복 알림 방지 - 최근 10분 이내 같은 알림은 생성하지 않음
        $stmt = $pdo->prepare("
            SELECT notification_id 
            FROM notifications 
            WHERE user_id = ? 
            AND from_user_id = ? 
            AND type = ? 
            AND target_id <=> ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            LIMIT 1
        ");
        $stmt->execute([$user_id, $from_user_id, $type, $target_id]);
        
        if ($stmt->fetch()) {
            // 이미 같은 알림이 존재하면 생성하지 않음
            return false;
        }
        
        // 팔로우 관련 알림은 읽지 않은 이전 알림 삭제
        if ($type === 'follow_request' || $type === 'follow_accept') {
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE user_id = ? 
                AND from_user_id = ? 
                AND type = ? 
                AND is_read = 0
            ");
            $stmt->execute([$user_id, $from_user_id, $type]);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, target_id, is_read)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$user_id, $from_user_id, $type, $target_id]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * 읽지 않은 알림 개수 조회
 */
function getUnreadNotificationCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

/**
 * 알림 읽음 표시 (개선)
 */
function markNotificationAsRead($pdo, $notification_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE notification_id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (Exception $e) {
        error_log("알림 읽음 표시 실패: " . $e->getMessage());
        return false;
    }
}

/**
 * 모든 알림 읽음 표시
 */
function markAllNotificationsAsRead($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        return $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("전체 알림 읽음 표시 실패: " . $e->getMessage());
        return false;
    }
}

/**
 * 알림 삭제 (개선)
 */
function deleteNotification($pdo, $notification_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE notification_id = ? AND user_id = ?
        ");
        $result = $stmt->execute([$notification_id, $user_id]);
        return $result && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("알림 삭제 실패: " . $e->getMessage());
        return false;
    }
}

/**
 * 비공개 계정 확인
 */
function isPrivateAccount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT is_private FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? (bool)$user['is_private'] : false;
}

/**
 * 팔로우 관계 확인
 */
function getFollowStatus($pdo, $follower_id, $following_id) {
    $stmt = $pdo->prepare("
        SELECT status FROM follows 
        WHERE follower_id = ? AND following_id = ?
    ");
    $stmt->execute([$follower_id, $following_id]);
    $follow = $stmt->fetch();
    
    if (!$follow) {
        return 'none';
    }
    return $follow['status'];
}

/**
 * 사용자가 팔로우 중인지 확인
 */
function isFollowing($pdo, $follower_id, $following_id) {
    return getFollowStatus($pdo, $follower_id, $following_id) === 'accepted';
}

/**
 * 콘텐츠 열람 권한 확인
 */
function canViewContent($pdo, $viewer_id, $content_owner_id) {
    if ($viewer_id == $content_owner_id) {
        return true;
    }
    
    if (!isPrivateAccount($pdo, $content_owner_id)) {
        return true;
    }
    
    return isFollowing($pdo, $viewer_id, $content_owner_id);
}

/**
 * 게시물 접근 권한 확인
 */
function canViewPost($pdo, $post_id, $viewer_user_id = null) {
    $stmt = $pdo->prepare("
        SELECT p.user_id, u.is_private
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        return false;
    }
    
    $post_owner_id = $post['user_id'];
    $is_private = (bool)$post['is_private'];
    
    if ($viewer_user_id && $viewer_user_id == $post_owner_id) {
        return true;
    }
    
    if ($is_private) {
        if (!$viewer_user_id) {
            return false;
        }
        
        $status = getFollowStatus($pdo, $viewer_user_id, $post_owner_id);
        return $status === 'accepted';
    }
    
    return true;
}

/**
 * 팔로우 요청 목록 가져오기
 */
function getFollowRequests($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            f.follow_id,
            f.follower_id,
            f.requested_at,
            u.user_id,
            u.username,
            u.nickname,
            u.profile_img,
            u.bio
        FROM follows f
        JOIN users u ON f.follower_id = u.user_id
        WHERE f.following_id = ? 
        AND f.status = 'pending'
        ORDER BY f.requested_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 팔로우 요청 개수 조회
 */
function getFollowRequestCount($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM follows 
        WHERE following_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

/**
 * 팔로우 요청 수락
 */
function acceptFollowRequest($pdo, $follow_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT follower_id, following_id, status 
            FROM follows 
            WHERE follow_id = ? AND following_id = ? AND status = 'pending'
        ");
        $stmt->execute([$follow_id, $user_id]);
        $follow = $stmt->fetch();
        
        if (!$follow) {
            return false;
        }
        
        $stmt = $pdo->prepare("UPDATE follows SET status = 'accepted' WHERE follow_id = ?");
        $stmt->execute([$follow_id]);
        
        // 기존 팔로우 요청 알림 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? 
            AND from_user_id = ? 
            AND type = 'follow_request'
        ");
        $stmt->execute([$user_id, $follow['follower_id']]);
        
        // 수락 알림 생성
        createNotification($pdo, $follow['follower_id'], $user_id, 'follow_accept');
        
        return true;
    } catch (Exception $e) {
        error_log("팔로우 요청 수락 실패: " . $e->getMessage());
        return false;
    }
}

/**
 * 팔로우 요청 거절
 */
function rejectFollowRequest($pdo, $follow_id, $user_id) {
    try {
        // 팔로우 레코드 가져오기
        $stmt = $pdo->prepare("
            SELECT follower_id 
            FROM follows 
            WHERE follow_id = ? AND following_id = ? AND status = 'pending'
        ");
        $stmt->execute([$follow_id, $user_id]);
        $follow = $stmt->fetch();
        
        if (!$follow) {
            return false;
        }
        
        // 팔로우 레코드 삭제
        $stmt = $pdo->prepare("
            DELETE FROM follows 
            WHERE follow_id = ? AND following_id = ? AND status = 'pending'
        ");
        $stmt->execute([$follow_id, $user_id]);
        
        // 관련 알림 삭제
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? 
            AND from_user_id = ? 
            AND type = 'follow_request'
        ");
        $stmt->execute([$user_id, $follow['follower_id']]);
        
        return true;
    } catch (Exception $e) {
        error_log("팔로우 요청 거절 실패: " . $e->getMessage());
        return false;
    }
}

/**
 * 알림 목록 가져오기
 */
function getNotifications($pdo, $user_id, $limit = 50, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            u.user_id as from_user_id,
            u.username as from_username,
            u.nickname as from_nickname,
            u.profile_img as from_profile_img,
            p.content as post_content,
            c.content as comment_content,
            CASE 
                WHEN n.type = 'like' THEN p.post_id
                WHEN n.type = 'comment' THEN c.post_id
                WHEN n.type = 'reply' THEN c.post_id
                ELSE NULL 
            END as related_post_id
        FROM notifications n
        JOIN users u ON n.from_user_id = u.user_id
        LEFT JOIN posts p ON n.type = 'like' AND n.target_id = p.post_id
        LEFT JOIN comments c ON n.type IN ('comment', 'reply') AND n.target_id = c.comment_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/**
 * 알림 메시지 생성
 */
function getNotificationMessage($notification) {
    $nickname = htmlspecialchars($notification['from_nickname']);
    
    switch($notification['type']) {
        case 'follow_request':
            return "<strong>{$nickname}</strong>님이 팔로우 요청을 보냈습니다.";
            
        case 'follow_accept':
            return "<strong>{$nickname}</strong>님이 팔로우 요청을 수락했습니다.";
            
        case 'like':
            return "<strong>{$nickname}</strong>님이 회원님의 게시물을 좋아합니다.";
            
        case 'comment':
            // 댓글 내용을 직접 표시
            if (!empty($notification['comment_content'])) {
                $content = htmlspecialchars($notification['comment_content']);
                // 긴 댓글은 잘라서 표시
                if (mb_strlen($content) > 100) {
                    $content = mb_substr($content, 0, 100) . '...';
                }
                return "<strong>{$nickname}</strong>: {$content}";
            }
            return "<strong>{$nickname}</strong>님이 회원님의 게시물에 댓글을 남겼습니다.";
            
        case 'reply':
            // 답글 내용을 직접 표시
            if (!empty($notification['comment_content'])) {
                $content = htmlspecialchars($notification['comment_content']);
                // 긴 답글은 잘라서 표시
                if (mb_strlen($content) > 100) {
                    $content = mb_substr($content, 0, 100) . '...';
                }
                return "<strong>{$nickname}</strong>: {$content}";
            }
            return "<strong>{$nickname}</strong>님이 회원님의 댓글에 답글을 남겼습니다.";
            
        default:
            return "<strong>{$nickname}</strong>님의 알림";
    }
}
/**
 * 알림 링크 생성
 */
function getNotificationLink($notification, $base_url, $pdo_param = null) {
    if ($pdo_param === null) {
        global $pdo;
        $pdo_param = $pdo;
    }
    
    switch($notification['type']) {
        case 'follow_request':
            return $base_url . '/pages/follow_requests.php';
        case 'follow_accept':
            return $base_url . '/pages/user_profile.php?id=' . $notification['from_user_id'];
        case 'like':
        case 'comment':
            $stmt = $pdo_param->prepare("SELECT post_id FROM posts WHERE post_id = ?");
            $stmt->execute([$notification['target_id']]);
            if ($stmt->fetch()) {
                return $base_url . '/pages/post_detail.php?id=' . $notification['target_id'];
            }
            $stmt = $pdo_param->prepare("SELECT post_id FROM comments WHERE comment_id = ?");
            $stmt->execute([$notification['target_id']]);
            $comment = $stmt->fetch();
            if ($comment) {
                return $base_url . '/pages/post_detail.php?id=' . $comment['post_id'];
            }
            return $base_url . '/pages/index.php';
        case 'reply':
            $stmt = $pdo_param->prepare("SELECT post_id FROM comments WHERE comment_id = ?");
            $stmt->execute([$notification['target_id']]);
            $comment = $stmt->fetch();
            if ($comment) {
                return $base_url . '/pages/post_detail.php?id=' . $comment['post_id'];
            }
            return $base_url . '/pages/index.php';
        default:
            return $base_url . '/pages/index.php';
    }
}
?>