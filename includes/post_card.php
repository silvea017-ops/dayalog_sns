<?php
// post_card.php - 재사용 가능한 게시물 카드 컴포넌트
// 이 파일은 $post 변수를 받아서 게시물 카드를 렌더링합니다

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
$stmt->execute([$post['post_id']]);
$like_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
$stmt->execute([$post['post_id']]);
$comment_count = $stmt->fetch()['count'];

$user_liked = false;
if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$_SESSION['user']['user_id'], $post['post_id']]);
    $user_liked = $stmt->fetch() ? true : false;
}

// 재귀적으로 댓글과 대댓글을 가져오는 함수
if (!function_exists('getCommentsRecursive')) {
    function getCommentsRecursive($pdo, $post_id, $parent_id = null, $depth = 0) {
        $stmt = $pdo->prepare("SELECT c.*, u.nickname, u.profile_img, u.user_id FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.post_id = ? AND " . ($parent_id ? "c.parent_comment_id = ?" : "c.parent_comment_id IS NULL") . " ORDER BY c.created_at ASC");
        
        if ($parent_id) {
            $stmt->execute([$post_id, $parent_id]);
        } else {
            $stmt->execute([$post_id]);
        }
        
        $comments = $stmt->fetchAll();
        $result = [];
        
        foreach ($comments as $comment) {
            $comment['depth'] = $depth;
            $comment['replies'] = getCommentsRecursive($pdo, $post_id, $comment['comment_id'], $depth + 1);
            $result[] = $comment;
        }
        
        return $result;
    }
}

$comments_tree = getCommentsRecursive($pdo, $post['post_id']);

// 댓글 렌더링 함수
if (!function_exists('renderComment')) {
    function renderComment($comment, $post_id, $current_user_id = null) {
        $indent_style = $comment['depth'] > 0 ? 'margin-left: ' . (min($comment['depth'], 5) * 48) . 'px;' : '';
        ?>
    <div class="comment-item" id="comment-<?php echo $comment['comment_id']; ?>" style="<?php echo $indent_style; ?>">
      <img src="<?php echo $comment['profile_img'] ? '../'.htmlspecialchars($comment['profile_img']) : '../assets/images/sample.png'; ?>" class="comment-avatar" alt="profile">
      <div class="comment-content">
        <strong><?php echo htmlspecialchars($comment['nickname']); ?></strong>
        <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
        <div class="comment-actions">
          <small class="text-muted"><?php echo htmlspecialchars($comment['created_at']); ?></small>
          <?php if($current_user_id): ?>
          <button class="reply-btn" onclick="showReplyForm(<?php echo $comment['comment_id']; ?>, '<?php echo htmlspecialchars($comment['nickname']); ?>')">답글</button>
          <?php endif; ?>
          <?php if($current_user_id && $current_user_id === $comment['user_id']): ?>
          <button type="button" class="delete-btn" onclick="deleteComment(<?php echo $comment['comment_id']; ?>, <?php echo $post_id; ?>)">삭제</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <?php if($current_user_id): ?>
    <div class="reply-form" id="reply-form-<?php echo $comment['comment_id']; ?>" style="display: none; <?php echo $indent_style; ?>">
      <form method="post" action="comment_add.php" onsubmit="handleCommentSubmit(event, <?php echo $post_id; ?>)">
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
        <input type="hidden" name="parent_comment_id" value="<?php echo $comment['comment_id']; ?>">
        <div class="d-flex gap-2">
          <img src="<?php echo $_SESSION['user']['profile_img'] ? '../'.htmlspecialchars($_SESSION['user']['profile_img']) : '../assets/images/sample.png'; ?>" class="comment-avatar" alt="profile">
          <input type="text" name="content" class="form-control" placeholder="답글을 입력하세요..." required>
          <button type="submit" class="btn btn-sm btn-primary">등록</button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="hideReplyForm(<?php echo $comment['comment_id']; ?>)">취소</button>
        </div>
      </form>
    </div>
    <?php endif; ?>
    
    <?php
    // 재귀적으로 대댓글 렌더링
    if (!empty($comment['replies'])) {
        foreach ($comment['replies'] as $reply) {
            renderComment($reply, $post_id, $current_user_id);
        }
    }
    }
}
?>

<div class="post-card mb-4" id="post-<?php echo $post['post_id']; ?>">
  <div class="post-header">
    <div class="d-flex align-items-center gap-3">
      <a href="user_profile.php?id=<?php echo $post['user_id']; ?>" class="profile-link">
        <img src="<?php echo $post['profile_img'] ? '../'.htmlspecialchars($post['profile_img']) : '../assets/images/sample.png'; ?>" class="profile-img-sm" alt="profile">
      </a>
      <div class="flex-grow-1">
        <a href="user_profile.php?id=<?php echo $post['user_id']; ?>" class="profile-link">
          <strong class="post-author"><?php echo htmlspecialchars($post['nickname']); ?></strong>
        </a>
        <div class="post-time"><?php echo htmlspecialchars($post['created_at']); ?></div>
      </div>
      
      <?php if(isset($_SESSION['user']) && $_SESSION['user']['user_id'] === $post['user_id']): ?>
      <div class="dropdown">
        <button class="post-menu-btn" type="button" data-bs-toggle="dropdown">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="1"></circle>
            <circle cx="12" cy="5" r="1"></circle>
            <circle cx="12" cy="19" r="1"></circle>
          </svg>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <button type="button" class="dropdown-item text-danger" onclick="deletePost(<?php echo $post['post_id']; ?>)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
              삭제
            </button>
          </li>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="post-content">
    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
    <?php if($post['image_path']): ?>
      <div class="post-image-container">
        <img src="<?php echo '../'.htmlspecialchars($post['image_path']); ?>" class="post-image" alt="post image">
      </div>
    <?php endif; ?>
  </div>

  <div class="post-stats">
    <span class="like-count-<?php echo $post['post_id']; ?>"><?php echo $like_count; ?>개의 좋아요</span>
    <span><?php echo $comment_count; ?>개의 댓글</span>
  </div>

  <div class="post-footer">
    <?php if(isset($_SESSION['user'])): ?>
    <button class="post-action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>" onclick="toggleLike(<?php echo $post['post_id']; ?>)">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $user_liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
      </svg>
      좋아요
    </button>
    <?php else: ?>
    <a href="login.php" class="post-action-btn">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
      </svg>
      좋아요
    </a>
    <?php endif; ?>
    
    <button class="post-action-btn" onclick="toggleComments(<?php echo $post['post_id']; ?>)">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      </svg>
      댓글
    </button>
    
    <button class="post-action-btn">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="18" cy="5" r="3"></circle>
        <circle cx="6" cy="12" r="3"></circle>
        <circle cx="18" cy="19" r="3"></circle>
        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
      </svg>
      공유
    </button>
  </div>

  <!-- 댓글 섹션 -->
  <div class="comments-section" id="comments-<?php echo $post['post_id']; ?>" style="display: none;">
    <div class="comments-list">
      <?php 
      foreach ($comments_tree as $comment) {
          renderComment($comment, $post['post_id'], $_SESSION['user']['user_id'] ?? null);
      }
      ?>
    </div>
    
    <?php if(isset($_SESSION['user'])): ?>
    <div class="comment-form">
      <form method="post" action="comment_add.php" onsubmit="handleCommentSubmit(event, <?php echo $post['post_id']; ?>)">
        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
        <div class="d-flex gap-2">
          <img src="<?php echo $_SESSION['user']['profile_img'] ? '../'.htmlspecialchars($_SESSION['user']['profile_img']) : '../assets/images/sample.png'; ?>" class="comment-avatar" alt="profile">
          <input type="text" name="content" class="form-control" placeholder="댓글을 입력하세요..." required>
          <button type="submit" class="btn btn-primary">등록</button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <div class="text-center py-3">
      <a href="login.php">로그인</a>하여 댓글을 작성하세요
    </div>
    <?php endif; ?>
  </div>
</div>