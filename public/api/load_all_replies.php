<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$parent_id = $_GET['parent_id'] ?? null;
$post_id = $_GET['post_id'] ?? null;

if (!$parent_id || !$post_id) {
    exit;
}

$current_user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

// 재귀 함수 (index.php와 동일)
function renderCommentTreeAjax($pdo, $post_id, $parent_id, $depth = 1, $current_user = null) {
    $stmt = $pdo->prepare("SELECT c.*, u.nickname, u.profile_img FROM comments c JOIN users u ON c.user_id = u.user_id WHERE c.parent_comment_id = ? ORDER BY c.created_at ASC");
    $stmt->execute([$parent_id]);
    $comments = $stmt->fetchAll();
    
    foreach($comments as $comment) {
        $is_reply = $depth > 0;
        $has_children = false;
        
        // 자식 댓글이 있는지 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE parent_comment_id = ?");
        $stmt->execute([$comment['comment_id']]);
        $children_count = $stmt->fetch()['count'];
        $has_children = $children_count > 0;
        
        ?>
        <div class="comment-wrapper" id="comment-wrapper-<?php echo $comment['comment_id']; ?>">
          <div class="comment-item <?php echo $is_reply ? 'is-reply' : ''; ?>" id="comment-<?php echo $comment['comment_id']; ?>">
            <div class="comment-thread-line-container">
              <?php if($is_reply): ?>
                <div class="comment-thread-line"></div>
              <?php endif; ?>
              <?php if($has_children): ?>
                <div class="comment-thread-line-bottom"></div>
              <?php endif; ?>
            </div>
            
            <img src="<?php echo $comment['profile_img'] ? '../'.htmlspecialchars($comment['profile_img']) : '../assets/images/sample.png'; ?>" 
                 class="comment-avatar" alt="profile">
            
            <div class="comment-content">
              <div class="comment-header">
                <strong><?php echo htmlspecialchars($comment['nickname']); ?></strong>
                <small class="text-muted"><?php echo htmlspecialchars($comment['created_at']); ?></small>
              </div>
              <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
              <div class="comment-actions">
                <?php if($current_user): ?>
                <button class="reply-btn" onclick="showReplyForm(<?php echo $comment['comment_id']; ?>, '<?php echo htmlspecialchars($comment['nickname']); ?>')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                  </svg>
                  답글
                </button>
                <?php endif; ?>
                <?php if($current_user && $current_user['user_id'] === $comment['user_id']): ?>
                <form method="post" action="comment_delete.php" style="display: inline;">
                  <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                  <button type="submit" class="delete-btn" onclick="return confirm('댓글을 삭제하시겠습니까?');">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    삭제
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <?php if($current_user): ?>
          <!-- 답글 작성 폼 -->
          <div class="reply-form" id="reply-form-<?php echo $comment['comment_id']; ?>" style="display: none;">
            <div class="reply-form-thread-line"></div>
            <form method="post" action="comment_add.php">
              <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
              <input type="hidden" name="parent_comment_id" value="<?php echo $comment['comment_id']; ?>">
              <div class="d-flex gap-2 align-items-start">
                <img src="<?php echo $current_user['profile_img'] ? '../'.htmlspecialchars($current_user['profile_img']) : '../assets/images/sample.png'; ?>" 
                     class="comment-avatar" alt="profile">
                <div class="flex-grow-1">
                  <input type="text" name="content" class="form-control form-control-sm" placeholder="답글을 입력하세요..." required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary">등록</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="hideReplyForm(<?php echo $comment['comment_id']; ?>)">취소</button>
              </div>
            </form>
          </div>
          <?php endif; ?>
          
          <div class="replies-container" id="replies-<?php echo $comment['comment_id']; ?>">
            <?php
            // 재귀 호출
            renderCommentTreeAjax($pdo, $post_id, $comment['comment_id'], $depth + 1, $current_user);
            ?>
          </div>
        </div>
        <?php
    }
}

// 모든 답글 렌더링
renderCommentTreeAjax($pdo, $post_id, $parent_id, 1, $current_user);