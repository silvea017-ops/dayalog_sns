<?php
// functions/comment_privacy.php - 새로운 파일 생성

/**
 * 댓글 열람 권한 확인
 * @param PDO $pdo
 * @param int $comment_id 댓글 ID
 * @param int|null $viewer_id 열람하려는 사용자 ID (null = 비로그인)
 * @return array ['can_view' => bool, 'message' => string, 'comment_owner' => array]
 */
function canViewComment($pdo, $comment_id, $viewer_id = null) {
    // 댓글 정보 및 작성자 정보 가져오기
    $stmt = $pdo->prepare("
        SELECT c.*, u.user_id, u.is_private, u.nickname, u.username
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.comment_id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        return [
            'can_view' => false,
            'message' => '댓글을 찾을 수 없습니다.',
            'comment_owner' => null
        ];
    }
    
    $comment_owner_id = $comment['user_id'];
    $is_private = (bool)$comment['is_private'];
    
    // 본인 댓글은 항상 볼 수 있음
    if ($viewer_id && $viewer_id == $comment_owner_id) {
        return [
            'can_view' => true,
            'message' => '',
            'comment_owner' => $comment
        ];
    }
    
    // 댓글 작성자가 비공개 계정인 경우
    if ($is_private) {
        // 로그인하지 않은 경우
        if (!$viewer_id) {
            return [
                'can_view' => false,
                'message' => '비공개 계정의 댓글입니다.',
                'comment_owner' => $comment
            ];
        }
        
        // 팔로우 관계 확인
        require_once __DIR__ . '/notifications.php';
        $follow_status = getFollowStatus($pdo, $viewer_id, $comment_owner_id);
        
        if ($follow_status !== 'accepted') {
            return [
                'can_view' => false,
                'message' => '비공개 계정의 댓글입니다.',
                'comment_owner' => $comment
            ];
        }
    }
    
    return [
        'can_view' => true,
        'message' => '',
        'comment_owner' => $comment
    ];
}

/**
 * 댓글 출력용 HTML 생성 (비공개 처리 포함)
 * @param array $comment 댓글 데이터
 * @param int|null $viewer_id 현재 사용자 ID
 * @param PDO $pdo
 * @return string HTML
 */
function renderCommentWithPrivacy($pdo, $comment, $viewer_id = null) {
    $permission = canViewComment($pdo, $comment['comment_id'], $viewer_id);
    
    if (!$permission['can_view']) {
        // 비공개 댓글 블록 처리
        return '
        <div class="comment-item comment-private">
            <div class="comment-private-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <div class="comment-private-text">
                <strong>' . htmlspecialchars($permission['comment_owner']['nickname']) . '</strong>님의 댓글
                <div class="text-muted small">' . htmlspecialchars($permission['message']) . '</div>
            </div>
        </div>';
    }
    
    // 정상 댓글 출력 (기존 로직)
    return null; // 정상 댓글은 기존 렌더링 로직 사용
}
?>