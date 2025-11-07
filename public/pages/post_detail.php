<?php
// public/pages/post_detail.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/notifications.php';
require_once FUNCTIONS_PATH . '/comment_privacy.php';
require_once FUNCTIONS_PATH . '/block.php';

$post_id = $_GET['id'] ?? null;
if (!$post_id) {
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user']['user_id'] ?? null;

// 재귀 함수로 댓글 트리 렌더링
function renderCommentTree($pdo, $post_id, $parent_id = null, $depth = 0, $max_visible = 3) {
    $current_user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    $viewer_id = $current_user ? $current_user['user_id'] : null;
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.nickname, u.profile_img, u.user_id, u.is_private
        FROM comments c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.post_id = ? AND c.parent_comment_id " . ($parent_id ? "= ?" : "IS NULL") . "
        ORDER BY c.created_at ASC
    ");
    
    if ($parent_id) {
        $stmt->execute([$post_id, $parent_id]);
    } else {
        $stmt->execute([$post_id]);
    }
    
    $comments = $stmt->fetchAll();
    $total_count = count($comments);
    
    foreach($comments as $index => $comment):
        $is_nested = $depth > 0;
        $is_hidden = $index >= $max_visible;
        
        // 댓글 열람 권한 확인
        $permission = canViewComment($pdo, $comment['comment_id'], $viewer_id);
        $can_view = $permission['can_view'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE parent_comment_id = ?");
        $stmt->execute([$comment['comment_id']]);
        $children_count = $stmt->fetch()['count'];
        ?>
        
        <div class="comment-wrapper <?php echo $is_hidden ? 'hidden-comment' : ''; ?> <?php echo $is_nested ? 'nested-comment' : ''; ?>" 
             id="comment-wrapper-<?php echo $comment['comment_id']; ?>"
             data-depth="<?php echo $depth; ?>"
             style="<?php echo $is_hidden ? 'display: none;' : ''; ?>">
          
          <?php if (!$can_view): ?>
            <div class="comment-item comment-private">
              <div class="comment-private-block">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <div>
                  <strong><?php echo htmlspecialchars($comment['nickname']); ?></strong>님의 댓글
                  <div class="text-muted small"><?php echo htmlspecialchars($permission['message']); ?></div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="comment-item" id="comment-<?php echo $comment['comment_id']; ?>">
              <?php if($children_count > 0): ?>
                <div class="reply-line"></div>
              <?php endif; ?>
              
              <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $comment['user_id']; ?>">
                <img src="<?php echo getProfileImageUrl($comment['profile_img']); ?>" 
                     class="comment-avatar" alt="profile">
              </a>
              
              <div class="comment-content">
                <div class="comment-header">
                  <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $comment['user_id']; ?>" class="comment-author">
                    <strong><?php echo htmlspecialchars($comment['nickname']); ?></strong>
                  </a>
                  <small class="text-muted"><?php echo formatPostDate($comment['created_at']); ?></small>
                </div>
                <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                
                <div class="comment-actions">
                  <?php if($current_user): ?>
                  <button class="action-btn" onclick="showReplyForm(<?php echo $comment['comment_id']; ?>, '<?php echo htmlspecialchars($comment['nickname']); ?>')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    답글
                  </button>
                  <?php endif; ?>
                  
                  <?php if($current_user && $current_user['user_id'] === $comment['user_id']): ?>
                  <button class="action-btn text-danger" onclick="deleteComment(<?php echo $comment['comment_id']; ?>, <?php echo $post_id; ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    삭제
                  </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <?php if($current_user): ?>
           <div class="reply-form" id="reply-form-<?php echo $comment['comment_id']; ?>" style="display: none; margin-left: <?php echo $is_nested ? '48px' : '48px'; ?>;">
  <form method="post" action="<?php echo BASE_URL; ?>/api/comment_add.php" onsubmit="handleCommentSubmit(event, <?php echo $post_id; ?>)">
    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
    <input type="hidden" name="parent_comment_id" value="<?php echo $comment['comment_id']; ?>">
    <div class="reply-input-wrapper">
      <img src="<?php echo getProfileImageUrl($current_user['profile_img']); ?>" 
           class="comment-avatar" alt="profile">
      <div class="reply-input-container">
        <input type="text" name="content" class="form-control form-control-sm reply-input" 
               placeholder="@<?php echo htmlspecialchars($comment['nickname']); ?>님에게 답글..." 
               maxlength="1000" required>
        <div class="reply-input-footer">
          <span class="reply-char-counter">1000자 남음</span>
          <div class="reply-buttons">
            <button type="submit" class="btn btn-sm btn-primary">등록</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="hideReplyForm(<?php echo $comment['comment_id']; ?>)">취소</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
            <?php endif; ?>
          <?php endif; ?>
          
          <div class="replies-container" id="replies-<?php echo $comment['comment_id']; ?>">
            <?php 
            // 재귀 호출로 대댓글 렌더링
            renderCommentTree($pdo, $post_id, $comment['comment_id'], $depth + 1, 3); 
            ?>
          </div>
        </div>
    <?php endforeach;
    
    if ($total_count > $max_visible && $depth === 0):
        $hidden_count = $total_count - $max_visible;
        ?>
        <button class="load-more-comments" onclick="loadMoreComments(<?php echo $post_id; ?>, <?php echo $parent_id ?? 'null'; ?>)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
          답글 <?php echo $hidden_count; ?>개 더보기
        </button>
    <?php endif;
}

// 게시물 정보 가져오기
$stmt = $pdo->prepare("
    SELECT p.*, u.user_id, u.nickname, u.username, u.profile_img, u.is_private 
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.post_id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: index.php');
    exit;
}

// 게시물 미디어 가져오기
$stmt = $pdo->prepare("
  SELECT * FROM post_images 
  WHERE post_id = ? 
  ORDER BY image_order ASC
");
$stmt->execute([$post_id]);
$post_media = $stmt->fetchAll();

// 좋아요 수
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
$stmt->execute([$post_id]);
$like_count = $stmt->fetch()['count'];

// 댓글 수
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
$stmt->execute([$post_id]);
$comment_count = $stmt->fetch()['count'];

// 현재 사용자가 좋아요 했는지
$user_liked = false;
if ($current_user_id) {
    $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$current_user_id, $post_id]);
    $user_liked = $stmt->fetch() ? true : false;
}
require_once INCLUDES_PATH . '/header.php'; ?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">

<?php
// redirect 파라미터 처리
$redirect_url = $_GET['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/pages/index.php';

// 상대 경로면 BASE_URL과 결합
if (!preg_match('#^https?://#', $redirect_url)) {
    // 앞에 슬래시가 없으면 추가
    if (substr($redirect_url, 0, 1) !== '/') {
        $redirect_url = '/' . $redirect_url;
    }
    $redirect_url = rtrim(BASE_URL, '/') . $redirect_url;
}
?>

<div class="mb-3">
  <a href="<?php echo htmlspecialchars($redirect_url); ?>"
     class="text-decoration-none text-muted d-flex align-items-center gap-2 back-button">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="19" y1="12" x2="5" y2="12"></line>
      <polyline points="12 19 5 12 12 5"></polyline>
    </svg>
    뒤로가기
  </a>
</div>
      <!-- 게시물 카드 -->
      <div class="post-card mb-4" id="post-<?php echo $post['post_id']; ?>">
        <div class="post-header">
          <div class="d-flex align-items-center gap-3">
            <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $post['user_id']; ?>">
              <img src="<?php echo getProfileImageUrl($post['profile_img']); ?>" 
                   class="profile-img-sm" alt="profile">
            </a>
            <div class="flex-grow-1">
              <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $post['user_id']; ?>">
                <strong class="post-author"><?php echo htmlspecialchars($post['nickname']); ?></strong>
              </a>
              <div class="post-time"><?php echo htmlspecialchars(formatPostDate($post['created_at'])); ?></div>
            </div>
            
            <?php if($current_user_id && $current_user_id === $post['user_id']): ?>
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
          <?php if($post['content']): ?>
            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
          <?php endif; ?>
          
          <?php if($post_media): ?>
            <div class="post-media-grid" data-count="<?php echo count($post_media); ?>">
              <?php foreach($post_media as $media): ?>
                <div class="post-media-item" data-media-type="<?php echo $media['media_type']; ?>">
                  <?php if($media['media_type'] === 'video'): ?>
                    <video 
                      src="<?php echo BASE_URL . '/' . htmlspecialchars($media['image_path']); ?>" 
                      controls
                      preload="metadata"
                      onclick="event.stopPropagation(); openMediaModal(<?php echo $post['post_id']; ?>, <?php echo array_search($media, $post_media); ?>)">
                    </video>
                  <?php else: ?>
                    <img 
                      src="<?php echo BASE_URL . '/' . htmlspecialchars($media['image_path']); ?>" 
                      alt="post media"
                      onclick="event.stopPropagation(); openMediaModal(<?php echo $post['post_id']; ?>, <?php echo array_search($media, $post_media); ?>)">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="post-stats">
          <span class="like-count-<?php echo $post['post_id']; ?>"><?php echo $like_count; ?>개의 좋아요</span>
          <span><?php echo $comment_count; ?>개의 댓글</span>
        </div>

        <div class="post-footer">
          <?php if($current_user_id): ?>
          <button class="post-action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>" 
                  onclick="toggleLike(<?php echo $post['post_id']; ?>)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $user_liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            좋아요
          </button>
          <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/pages/login.php" class="post-action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            좋아요
          </a>
          <?php endif; ?>
          
          <button class="post-action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            댓글
          </button>
          
          <button class="post-action-btn" onclick="sharePost(<?php echo $post['post_id']; ?>)">
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
        <div class="comments-section">
          <div class="comments-list">
            <?php renderCommentTree($pdo, $post['post_id'], null, 0, 3); ?>
          </div>
          
          <?php if($current_user_id): ?>
         <div class="comment-form">
  <form method="post" action="<?php echo BASE_URL; ?>/api/comment_add.php" onsubmit="handleCommentSubmit(event, <?php echo $post['post_id']; ?>)">
    <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
    <div class="comment-input-wrapper">
      <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>" 
           class="comment-avatar" alt="profile">
      <div class="comment-input-container">
        <textarea name="content" class="comment-input" placeholder="댓글을 입력하세요..." maxlength="1000" required></textarea>
        <div class="comment-input-footer">
          <span class="comment-char-counter">1000자 남음</span>
          <button type="submit" class="btn btn-primary btn-sm">등록</button>
        </div>
      </div>
    </div>
  </form>
</div>
          <?php else: ?>
          <div class="text-center py-3">
            <a href="<?php echo BASE_URL; ?>/pages/login.php">로그인</a>하여 댓글을 작성하세요
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<style>
/* 답글 입력 래퍼 */
.reply-input-wrapper {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.reply-input-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reply-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 14px;
}

.reply-input-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.reply-char-counter {
  font-size: 12px;
  color: var(--text-secondary);
}

.reply-char-counter.warning {
  color: #f59e0b;
  font-weight: 600;
}

.reply-char-counter.danger {
  color: #dc3545;
  font-weight: 600;
}

.reply-buttons {
  display: flex;
  gap: 4px;
}

/* 댓글 글자수 카운터 스타일 */
.comment-char-counter {
  font-size: 13px;
  color: var(--text-secondary);
}

.comment-char-counter.warning {
  color: #f59e0b;
  font-weight: 600;
}

.comment-char-counter.danger {
  color: #dc3545;
  font-weight: 600;
}

/* 댓글 폼 버튼 가로 배치 */
.comment-form .d-flex,
.reply-form .d-flex {
  display: flex !important;
  flex-direction: row !important;
  align-items: flex-start;
}

.comment-form input[type="text"],
.reply-form input[type="text"] {
  flex: 1;
}

.comment-form button,
.reply-form button {
  flex-shrink: 0;
  white-space: nowrap;
}

.back-button {
  padding: 8px 12px;
  border-radius: 8px;
  transition: all 0.2s;
  display: inline-flex !important;
  width: fit-content;
}

.back-button:hover {
  background: var(--bg-hover);
  color: var(--text-primary) !important;
}

/* 미디어 그리드 */
.post-media-grid {
  display: grid;
  gap: 4px;
  margin-top: 12px;
  border-radius: 12px;
  overflow: hidden;
}

.post-media-grid[data-count="1"] {
  grid-template-columns: 1fr;
}

.post-media-grid[data-count="1"] .post-media-item {
  aspect-ratio: 16/9;
  max-height: 500px;
}

.post-media-grid[data-count="2"] {
  grid-template-columns: 1fr 1fr;
}

.post-media-grid[data-count="3"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

.post-media-grid[data-count="3"] .post-media-item:first-child {
  grid-row: 1 / 3;
}

.post-media-grid[data-count="4"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

.post-media-grid[data-count="5"],
.post-media-grid[data-count="6"],
.post-media-grid[data-count="7"],
.post-media-grid[data-count="8"] {
  grid-template-columns: repeat(3, 1fr);
}

.post-media-grid[data-count="5"] .post-media-item:first-child,
.post-media-grid[data-count="6"] .post-media-item:first-child {
  grid-column: 1 / 3;
  grid-row: 1 / 3;
}

.post-media-item {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--bg-secondary);
  cursor: pointer;
}

.post-media-item img,
.post-media-item video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.post-media-item:hover img,
.post-media-item:hover video {
  transform: scale(1.05);
}

/* 비디오 재생 버튼 오버레이 */
.post-media-item[data-media-type="video"]::after {
  content: '▶';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 60px;
  height: 60px;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
  pointer-events: none;
}

@media (max-width: 768px) {
  .post-media-grid[data-count="3"],
  .post-media-grid[data-count="4"] {
    grid-template-columns: 1fr 1fr;
  }
  
  .post-media-grid[data-count="3"] .post-media-item:first-child {
    grid-row: auto;
  }
}

/* 비공개 댓글 스타일 */
.comment-private {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
}

.comment-private-block {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--text-secondary);
}

.comment-private-block svg {
  opacity: 0.5;
  flex-shrink: 0;
}

.comment-private-block strong {
  color: var(--text-primary);
  display: block;
}

.comment-private-block .text-muted {
  font-size: 13px;
  margin-top: 2px;
}

/* 댓글 스타일 */
.comment-wrapper {
  position: relative;
  margin-bottom: 12px;
}

.comment-item {
  display: flex;
  gap: 12px;
  position: relative;
  padding: 8px 0;
}

.reply-line {
  position: absolute;
  left: 18px;
  top: 48px;
  bottom: -12px;
  width: 2px;
  background: var(--border-color);
}

.comment-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.comment-content {
  flex: 1;
  min-width: 0;
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.comment-author {
  text-decoration: none;
  color: var(--text-primary);
}

.comment-author:hover {
  text-decoration: underline;
}

.comment-text {
  margin: 0 0 8px 0;
  font-size: 15px;
  line-height: 1.5;
  word-wrap: break-word;
  word-break: break-word;
}

.comment-actions {
  display: flex;
  gap: 16px;
  align-items: center;
  flex-wrap: wrap;
}

.action-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 13px;
  cursor: pointer;
  padding: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.2s;
}

.action-btn:hover {
  color: var(--primary-color);
}

.action-btn.text-danger:hover {
  color: #dc3545 !important;
}

.load-more-comments {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  margin: 8px 0 8px 48px;
  background: none;
  border: none;
  color: var(--primary-color);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.2s;
}

.load-more-comments:hover {
  background: var(--bg-hover);
}

.reply-form {
  margin-top: 8px;
  margin-bottom: 8px;
}

.post-stats {
  padding: 12px 20px;
  border-top: 1px solid var(--border-color);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  gap: 16px;
  font-size: 14px;
  color: var(--text-secondary);
}

.comments-section {
  padding: 20px;
  border-top: 1px solid var(--border-color);
}

.comments-list {
  margin-bottom: 16px;
}

.comment-form {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border-color);
}

/* 댓글 입력 래퍼 - 개선된 레이아웃 */
.comment-input-wrapper {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.comment-input-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.comment-input {
  width: 100%;
  min-height: 80px;
  padding: 12px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 14px;
  resize: vertical;
  font-family: inherit;
}

.comment-input:focus {
  outline: none;
  border-color: var(--primary-color);
}

.comment-input-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.like-btn.liked {
  color: #e0245e;
}

/* 반응형 처리 */
@media (max-width: 576px) {
  .comment-input-wrapper,
  .reply-input-wrapper {
    gap: 8px;
  }
  
  .comment-avatar {
    width: 32px;
    height: 32px;
  }
  
  .comment-input {
    min-height: 60px;
    padding: 10px;
    font-size: 13px;
  }
  
  .reply-input {
    padding: 8px 10px;
    font-size: 13px;
  }
  
  .comment-input-footer,
  .reply-input-footer {
    flex-wrap: wrap;
  }
  
  .load-more-comments {
    margin-left: 40px;
    font-size: 13px;
  }
}
</style>

<script>
// 좋아요 토글
function toggleLike(postId) {
  fetch('<?php echo BASE_URL; ?>/api/like_toggle.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'post_id=' + postId
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const likeBtn = document.querySelector(`#post-${postId} .like-btn`);
      const likeCount = document.querySelector(`.like-count-${postId}`);
      const svg = likeBtn.querySelector('svg');
      
      if (data.liked) {
        likeBtn.classList.add('liked');
        svg.setAttribute('fill', 'currentColor');
      } else {
        likeBtn.classList.remove('liked');
        svg.setAttribute('fill', 'none');
      }
      
      likeCount.textContent = data.like_count + '개의 좋아요';
    }
  })
  .catch(err => console.error('Like error:', err));
}

// 답글 폼 표시
function showReplyForm(commentId, nickname) {
  document.querySelectorAll('.reply-form').forEach(form => {
    form.style.display = 'none';
  });
  
  const replyForm = document.getElementById('reply-form-' + commentId);
  if (replyForm) {
    replyForm.style.display = 'block';
    const input = replyForm.querySelector('input[name="content"]');
    input.focus();
  }
}

// 답글 폼 숨기기
function hideReplyForm(commentId) {
  const replyForm = document.getElementById('reply-form-' + commentId);
  if (replyForm) {
    replyForm.style.display = 'none';
    const input = replyForm.querySelector('input[name="content"]');
    input.value = '';
  }
}

// 댓글 제출 처리
function handleCommentSubmit(event, postId) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  fetch(form.action, {
    method: 'POST',
    body: formData
  })
  .then(() => {
    location.reload();
  })
  .catch(err => {
    console.error('Comment error:', err);
    alert('댓글 등록에 실패했습니다.');
  });
}

// 게시물 삭제
function deletePost(postId) {
  if (!confirm('정말 삭제하시겠습니까?')) return;
  
  const formData = new FormData();
  formData.append('id', postId);
  
  fetch('<?php echo BASE_URL; ?>/api/post_delete.php', {
    method: 'POST',
    body: formData
  })
  .then(() => {
    alert('게시물이 삭제되었습니다.');
    window.location.href = '<?php echo BASE_URL; ?>/pages/index.php';
  })
  .catch(err => {
    console.error('Delete error:', err);
    alert('삭제에 실패했습니다.');
  });
}

// 댓글 삭제
function deleteComment(commentId, postId) {
  if (!confirm('댓글을 삭제하시겠습니까?')) return;
  
  const formData = new FormData();
  formData.append('comment_id', commentId);
  
  fetch('<?php echo BASE_URL; ?>/api/comment_delete.php', {
    method: 'POST',
    body: formData
  })
  .then(() => {
    location.reload();
  })
  .catch(err => {
    console.error('Delete error:', err);
    alert('삭제에 실패했습니다.');
  });
}

// 더보기 버튼
function loadMoreComments(postId, parentId) {
  const hiddenComments = document.querySelectorAll(`#post-${postId} .hidden-comment`);
  hiddenComments.forEach(comment => {
    comment.style.display = 'block';
    comment.classList.remove('hidden-comment');
  });
  
  event.target.style.display = 'none';
}

// 공유 기능
function sharePost(postId) {
  const url = window.location.origin + '<?php echo BASE_URL; ?>/pages/post_detail.php?id=' + postId;
  
  if (navigator.share) {
    navigator.share({ title: '게시물 공유', url: url });
  } else {
    navigator.clipboard.writeText(url).then(() => {
      alert('링크가 복사되었습니다!');
    });
  }
}
// 페이지 로드 시 모든 입력창 설정
document.addEventListener('DOMContentLoaded', function() {
  // 댓글 입력창 글자수 카운터
  function setupCharCounter(input, counter, maxLength = 1000) {
    if (!input || !counter) return;
    
    input.addEventListener('input', function() {
      const remaining = maxLength - this.value.length;
      counter.textContent = `${remaining}자 남음`;
      
      counter.classList.remove('warning', 'danger');
      if (remaining < 100) counter.classList.add('warning');
      if (remaining < 0) counter.classList.add('danger');
    });
  }
  
  // 메인 댓글 입력창
  document.querySelectorAll('.comment-input').forEach(textarea => {
    const container = textarea.closest('.comment-input-container');
    const counter = container?.querySelector('.comment-char-counter');
    setupCharCounter(textarea, counter);
  });
  
  // 동적으로 생성되는 답글 폼을 위한 MutationObserver
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) {
          // 답글 입력창
          const replyInputs = node.querySelectorAll?.('.reply-input');
          replyInputs?.forEach(input => {
            const container = input.closest('.reply-input-container');
            const counter = container?.querySelector('.reply-char-counter');
            if (!input.dataset.listenerAdded) {
              setupCharCounter(input, counter);
              input.dataset.listenerAdded = 'true';
            }
          });
        }
      });
    });
  });
  
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>