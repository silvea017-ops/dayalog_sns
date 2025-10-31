<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

if (isset($_SESSION['user']) && isset($_SESSION['user']['show_all_tab']) && !$_SESSION['user']['show_all_tab']) {
    header('Location: ' . BASE_URL . '/pages/following.php');
    exit;
}

require_once INCLUDES_PATH . '/header.php';
require_once FUNCTIONS_PATH . '/notifications.php';
require_once FUNCTIONS_PATH . '/date_helper.php';
require_once __DIR__ . '/../../functions/comment_privacy.php';

function getRelativeTime($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->getTimestamp() - $past->getTimestamp();
    
    if ($diff < 60) return '방금';
    elseif ($diff < 3600) return floor($diff / 60) . '분';
    elseif ($diff < 86400) return floor($diff / 3600) . '시간';
    elseif ($diff < 604800) return floor($diff / 86400) . '일';
    else return $past->format('n월 j일');
}
// 재귀 함수로 댓글 트리 렌더링
function renderCommentTree($pdo, $post_id, $parent_id = null, $depth = 0, $max_visible = 3) {
    $current_user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    $viewer_id = $current_user ? $current_user['user_id'] : null;
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.nickname, u.username, u.profile_img, u.user_id, u.is_private
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
        
        // 자식 댓글 개수 확인
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
              <!-- 자식 댓글이 있으면 연결선 표시 -->
              <?php if($children_count > 0): ?>
                <div class="reply-line"></div>
              <?php endif; ?>
              
              <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $comment['user_id']; ?>">
                <img src="<?php echo getProfileImageUrl($comment['profile_img']); ?>" 
                     class="comment-avatar" alt="profile">
              </a>
              
              <div class="comment-content">
                <div class="comment-header">
                  <div class="comment-user-info">
                    <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $comment['user_id']; ?>" class="comment-author">
                      <strong><?php echo htmlspecialchars($comment['nickname']); ?></strong>
                      <?php if($comment['is_private']): ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-1" style="vertical-align: middle;">
                          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                      <?php endif; ?>
                    </a>
                    <span class="comment-username">@<?php echo htmlspecialchars($comment['username']); ?></span>
                    <span class="comment-dot">·</span>
                    <small class="comment-time" data-time="<?php echo $comment['created_at']; ?>">
                      <?php echo getRelativeTime($comment['created_at']); ?>
                    </small>
                  </div>
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
                <div class="d-flex gap-2 align-items-start">
                  <img src="<?php echo getProfileImageUrl($current_user['profile_img']); ?>" 
                       class="comment-avatar" alt="profile">
                  <input type="text" name="content" class="form-control form-control-sm" 
                         placeholder="@<?php echo htmlspecialchars($comment['nickname']); ?>님에게 답글..." required>
                  <button type="submit" class="btn btn-sm btn-primary">등록</button>
                  <button type="button" class="btn btn-sm btn-secondary" onclick="hideReplyForm(<?php echo $comment['comment_id']; ?>)">취소</button>
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

// 게시물 가져오기

$current_user_id = $_SESSION['user']['user_id'] ?? null;

if ($current_user_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nickname, u.username, u.profile_img, u.user_id, u.is_private
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.user_id = ? 
           OR u.is_private = 0 
           OR EXISTS (
               SELECT 1 FROM follows f 
               WHERE f.follower_id = ? 
               AND f.following_id = p.user_id 
               AND f.status = 'accepted'
           )
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nickname, u.username, u.profile_img, u.user_id, u.is_private
        FROM posts p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE u.is_private = 0
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
}

$posts = $stmt->fetchAll();
?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">

      <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
          <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']);
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">
          <?php 
            echo htmlspecialchars($_SESSION['error_message']); 
            unset($_SESSION['error_message']);
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <?php if(isset($_SESSION['user'])): ?>
      <!-- 글 작성 카드 -->
      <div class="create-post-card mb-4" id="createPostCard">
        <form method="post" action="<?php echo BASE_URL; ?>/api/post_create.php" enctype="multipart/form-data" id="mainPostForm">
          <div class="d-flex align-items-start gap-3">
            <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $_SESSION['user']['user_id']; ?>">
              <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>"
                   class="profile-img-sm" alt="profile">
            </a>
            <div class="flex-grow-1">
              <textarea name="content" class="post-textarea" placeholder="무슨 생각을 하고 있나요?" maxlength="1000"></textarea>
              
              <!-- 이미지 프리뷰 그리드 -->
              <div id="imagePreviewGrid" class="image-preview-grid" style="display:none;"></div>
              
              <div class="post-actions">
                <div class="d-flex align-items-center gap-3">
                  <label class="upload-btn">
                    <input type="file" name="images[]" accept="image/*,video/*" multiple hidden id="imageInput" max="8">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <circle cx="8.5" cy="8.5" r="1.5"></circle>
                      <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    사진
                  </label>
                  <span class="char-counter text-muted small">1000자</span>
                </div>
                <button type="submit" class="btn-post">게시</button>
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- 플로팅 작성 버튼 (모바일) -->
      <button class="floating-create-btn" onclick="openCreateModal()">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 20h9"></path>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
        </svg>
      </button>

      <!-- 글작성 모달 (모바일) -->
      <div class="create-post-modal" id="createModal">
        <div class="modal-content-wrapper">
          <div class="modal-header">
            <button class="modal-close-btn" onclick="closeCreateModal()">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
            <h3>새 게시물</h3>
            <div style="width: 24px;"></div>
          </div>
          
          <div class="modal-body" id="modalBody">
            <form method="post" action="<?php echo BASE_URL; ?>/api/post_create.php" enctype="multipart/form-data" id="modalPostForm">
              <div class="d-flex align-items-start gap-3">
                <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>"
                     class="profile-img-sm" alt="profile">
                <div class="flex-grow-1">
                  <textarea name="content" class="post-textarea" placeholder="무슨 생각을 하고 있나요?"></textarea>
                  
                  <!-- 이미지 프리뷰 그리드 -->
                  <div id="modalImagePreviewGrid" class="image-preview-grid" style="display:none;"></div>
                </div>
              </div>
              
              <div class="modal-footer">
                <div class="modal-actions">
                  <label class="upload-btn">
                    <input type="file" name="images[]" accept="image/*,video/*" multiple hidden id="modalImageInput" max="8">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <circle cx="8.5" cy="8.5" r="1.5"></circle>
                      <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                  </label>
                </div>
                <button type="submit" class="btn-post">게시</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 새 글 알림 배너 -->
      <div class="new-posts-banner" id="newPostsBanner" style="display: none;">
        <button onclick="loadNewPosts()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="23 4 23 10 17 10"></polyline>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
          </svg>
          <span id="newPostsCount">0</span>개의 새 게시물 보기
        </button>
      </div>

      <!-- 피드 헤더 -->
      <div class="feed-header mb-4">
        <h4 class="mb-0">최신 피드</h4>
      </div>

      <?php if(empty($posts)): ?>
        <div class="empty-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z"></path>
            <polyline points="9 11 12 14 22 4"></polyline>
          </svg>
          <p>아직 게시물이 없습니다</p>
          <small>첫 번째 게시물을 작성해보세요!</small>
        </div>
      <?php endif; ?>

    <?php foreach($posts as $post): ?>
    <?php
    // 게시물 미디어 가져오기
    $stmt = $pdo->prepare("
      SELECT * FROM post_images 
      WHERE post_id = ? 
      ORDER BY image_order ASC
    ");
    $stmt->execute([$post['post_id']]);
    $post_media = $stmt->fetchAll();
        
        // 좋아요 수
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $stmt->execute([$post['post_id']]);
        $like_count = $stmt->fetch()['count'];
        
        // 댓글 수
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
        $stmt->execute([$post['post_id']]);
        $comment_count = $stmt->fetch()['count'];
        
        // 현재 사용자가 좋아요 했는지
        $user_liked = false;
        if (isset($_SESSION['user'])) {
            $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
            $stmt->execute([$_SESSION['user']['user_id'], $post['post_id']]);
            $user_liked = $stmt->fetch() ? true : false;
        }
        ?>
        
       <div class="post-card mb-4" id="post-<?php echo $post['post_id']; ?>" 
         onclick="goToPostDetail(<?php echo $post['post_id']; ?>, event)" 
         style="cursor: pointer;">
         <div class="post-header">
  <div class="d-flex align-items-center gap-3 flex-grow-1">
    <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $post['user_id']; ?>">
      <img src="<?php echo getProfileImageUrl($post['profile_img']); ?>" 
           class="profile-img-sm" alt="profile">
    </a>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $post['user_id']; ?>" class="post-author text-decoration-none">
          <strong><?php echo htmlspecialchars($post['nickname']); ?></strong>
        </a>
        <?php if($post['is_private']): ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        <?php endif; ?>
        <span class="text-muted">@<?php echo htmlspecialchars($post['username']); ?></span>
        <span class="text-muted">·</span>
        <span class="text-muted" data-time="<?php echo $post['created_at']; ?>">
          <?php echo getRelativeTime($post['created_at']); ?>
        </span>
      </div>
    </div>
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
    autoplay
    loop
    muted
    playsinline
    preload="metadata"
    controls
    onclick="event.stopPropagation(); toggleVideoPlay(this)"
    class="post-video">
  </video>
  <div class="play-overlay">▶</div>
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
            <div class="stats-left">
              <span class="like-count-<?php echo $post['post_id']; ?>"><?php echo $like_count; ?>개의 좋아요</span>
              <span><?php echo $comment_count; ?>개의 댓글</span>
            </div>
            <div class="stats-right">
              <div class="post-absolute-time"><?php echo formatPostDate($post['created_at']); ?></div>
            </div>
          </div>

          <div class="post-footer">
            <?php if(isset($_SESSION['user'])): ?>
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
            
            <button class="post-action-btn" onclick="toggleComments(<?php echo $post['post_id']; ?>)">
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
          <div class="comments-section" id="comments-<?php echo $post['post_id']; ?>" style="display: none;">
            <div class="comments-list">
              <?php renderCommentTree($pdo, $post['post_id'], null, 0, 3); ?>
            </div>
            
            <?php if(isset($_SESSION['user'])): ?>
            <div class="comment-form">
              <form method="post" action="<?php echo BASE_URL; ?>/api/comment_add.php" onsubmit="handleCommentSubmit(event, <?php echo $post['post_id']; ?>)">
                <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                <div class="comment-input-wrapper">
                  <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>" 
                       class="comment-avatar" alt="profile">
                  <div class="comment-input-container">
                    <textarea name="content" class="comment-input" placeholder="댓글을 입력하세요..." maxlength="1000" required></textarea>
                    <div class="comment-input-footer">
                      <span class="comment-char-counter">1000자</span>
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
      <?php endforeach; ?>

    </div>

    <!-- 사이드바 -->
    <div class="col-lg-4 d-none d-lg-block">
      <div class="sidebar-card sticky-top">
        <!-- <h5 class="sidebar-title">Dayalog</h5>
        <p class="sidebar-text">일상을 공유하는 감성 SNS</p> -->
        <?php if(isset($_SESSION['user'])): ?>
          <div class="user-widget">
            <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $_SESSION['user']['user_id']; ?>">
              <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>" 
                   class="profile-img-sm" alt="profile">
            </a>
            <div>
              <strong><?php echo htmlspecialchars($_SESSION['user']['nickname']); ?></strong>
              <div class="text-muted small">@<?php echo htmlspecialchars($_SESSION['user']['username']); ?></div>
            </div>
          </div>
          <?php
          $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM follows WHERE following_id = ? AND status = 'accepted'");
          $stmt->execute([$_SESSION['user']['user_id']]);
          $my_follower_count = $stmt->fetch()['count'];
          
          $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ? AND status = 'accepted'");
          $stmt->execute([$_SESSION['user']['user_id']]);
          $my_following_count = $stmt->fetch()['count'];
          
          $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM posts WHERE user_id = ?");
          $stmt->execute([$_SESSION['user']['user_id']]);
          $my_post_count = $stmt->fetch()['count'];
          ?>
          <div class="sidebar-stats mt-3">
            <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $_SESSION['user']['user_id']; ?>" class="stat-link">
              <strong><?php echo $my_post_count; ?></strong>
              <span>게시물</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/pages/follows.php?id=<?php echo $_SESSION['user']['user_id']; ?>&type=followers" class="stat-link">
              <strong><?php echo $my_follower_count; ?></strong>
              <span>팔로워</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/pages/follows.php?id=<?php echo $_SESSION['user']['user_id']; ?>&type=following" class="stat-link">
              <strong><?php echo $my_following_count; ?></strong>
              <span>팔로잉</span>
            </a>
          </div>
          <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="btn btn-outline-primary w-100 mt-3">프로필 편집</a>
        <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/pages/login.php" class="btn btn-primary w-100">로그인</a>
          <a href="<?php echo BASE_URL; ?>/pages/register.php" class="btn btn-outline-primary w-100 mt-2">회원가입</a>
        <?php endif; ?>
      </div>

  <!-- 캘린더 위젯 추가 -->
  <!-- <?php--> 
  <!--if (isset($_SESSION['user'])) {-->
  <!--    require_once INCLUDES_PATH . '/mini_calendar_widget.php'; -->
 <!-- }-->
 <!-- ?>-->
<!--</div> -->
    </div>
  </div>
</div>


<style>
  .upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: none;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  color: var(--text-secondary);
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.upload-btn:hover {
  background: var(--bg-hover);
  border-color: var(--primary-color);
  color: var(--primary-color);
}

/* 미디어 카운트 표시 */
.media-count-display {
  font-size: 13px;
  color: var(--text-secondary);
  margin-left: 8px;
}

.media-count-display.warning {
  color: #f59e0b;
  font-weight: 600;
}

.media-count-display.limit {
  color: #dc3545;
  font-weight: 600;
}

/* 포스트 카드 호버 효과 */
.post-card {
  transition: box-shadow 0.2s, transform 0.2s;
}

.post-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

/* 비디오 컨트롤 스타일 개선 */
video::-webkit-media-controls-panel {
  background-image: linear-gradient(transparent, rgba(0,0,0,0.7));
}

video::-webkit-media-controls-play-button,
video::-webkit-media-controls-mute-button,
video::-webkit-media-controls-fullscreen-button {
  filter: brightness(1.2);
}

/* 모바일 반응형 */
@media (max-width: 768px) {
  .post-media-grid[data-count="3"],
  .post-media-grid[data-count="4"] {
    grid-template-columns: 1fr 1fr;
  }
  
  .post-media-grid[data-count="3"] .post-media-item:first-child {
    grid-row: auto;
  }
  
  .media-viewer-modal .media-viewer-swiper img,
  .media-viewer-modal .media-viewer-swiper video {
    max-width: 100%;
    max-height: 60vh;
  }
}

/* 로딩 인디케이터 */
.media-loading {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 40px;
  height: 40px;
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: translate(-50%, -50%) rotate(360deg); }
}

/* 업로드 진행 바 */
.upload-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.upload-progress-bar {
  height: 100%;
  background: var(--primary-color);
  transition: width 0.3s;
}

/* 에러 상태 */
.preview-item.error {
  border: 2px solid #dc3545;
}

.preview-item.error::after {
  content: '!';
  position: absolute;
  top: 8px;
  left: 8px;
  width: 24px;
  height: 24px;
  background: #dc3545;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  z-index: 10;
}

/* 드래그 중 스타일 개선 */
.preview-item.dragging {
  opacity: 0.5;
  transform: scale(0.95);
}

.preview-item.drag-over {
  border: 2px dashed var(--primary-color);
  background: rgba(86, 105, 254, 0.1);
}
  .post-media-grid {
    display: grid;
  gap: 4px;
  margin-top: 12px;
  border-radius: 12px;
  overflow: hidden;
}.post-media-grid[data-count="1"] {
  grid-template-columns: 1fr;
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

.post-media-grid[data-count="1"] .post-media-item {
  aspect-ratio: 16/9;
  max-height: 500px;
}
.media-type-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  z-index: 5;
}

/* 프리뷰 아이템 비디오 스타일 */
.preview-item video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.preview-item[data-type="video"]::before {
  content: '▶';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 40px;
  height: 40px;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
  z-index: 3;
  pointer-events: none;
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

/* 영상 재생 버튼 (일시정지 상태에만 표시) */
.post-media-item[data-media-type="video"] {
  position: relative;
}

.post-media-item[data-media-type="video"] .play-overlay {
  content: '▶';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 60px;
  height: 60px;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 50%;
  display: none; /* 기본적으로 숨김 */
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
  pointer-events: none;
  z-index: 10;
}

.post-media-item[data-media-type="video"] video.paused + .play-overlay {
  display: flex;
}

/* 영상 호버 시 약간 어둡게 */
.post-media-item[data-media-type="video"]:hover::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.1);
  pointer-events: none;
  z-index: 5;
}
  /* 게시물 헤더 - 트위터 스타일 */
.post-header {
  padding: 16px 20px 8px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.post-header .profile-img-sm {
  flex-shrink: 0;
}

.post-user-info-container {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.post-user-info {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  line-height: 1.4;
}

.post-username {
  font-size: 15px;
  color: var(--text-secondary);
  font-weight: 400;
}

.post-dot {
  color: var(--text-secondary);
  font-size: 15px;
  line-height: 1;
}

.post-relative-time {
  font-size: 15px;
  color: var(--text-secondary);
  white-space: nowrap;
}

/* 댓글 사용자 정보 */
.comment-user-info {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.comment-username {
  font-size: 14px;
  color: var(--text-secondary);
  font-weight: 400;
}

.comment-dot {
  color: var(--text-secondary);
  font-size: 14px;
}

.comment-time {
  font-size: 14px;
  color: var(--text-secondary);
  white-space: nowrap;
}

/* 게시물 통계 - 왼쪽/오른쪽 분리 */
.post-stats {
  padding: 12px 20px;
  border-top: 1px solid var(--border-color);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  font-size: 14px;
  color: var(--text-secondary);
}

.stats-left {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.stats-right {
  display: flex;
  align-items: center;
  margin-left: auto;
}

.post-absolute-time {
  font-size: 13px;
  color: var(--text-secondary);
  white-space: nowrap;
  margin-right:10px;
}

/* 드래그 오버 효과 */
.create-post-card.drag-over,
.modal-body.drag-over {
  border: 2px dashed var(--primary-color);
  background: var(--bg-hover);
}

.align-items-start {
    align-items: center !important;
}

.post-header {
  padding: 16px 20px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

/* 사용자 이름 링크 - 밑줄 제거 */
.post-author-link {
  text-decoration: none !important;
  color: var(--text-primary);
}

.post-author-link:hover {
  text-decoration: none !important;
  color: var(--primary-color);
}

.post-author {
  font-size: 15px;
  font-weight: 600;
  display: block;
  margin-bottom: 2px;
}

/* 날짜를 사용자 이름 아래로 */
.post-time {
  font-size: 13px;
  color: var(--text-secondary);
  display: block;
  margin-top: 2px;
}

/* 플로팅 작성 버튼 (모바일) */
.floating-create-btn {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--primary-color);
  color: white;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: none;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 1000;
  transition: all 0.3s;
}

.floating-create-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.floating-create-btn:active {
  transform: scale(0.95);
}

/* 글 작성 카드 */
.create-post-card {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: var(--shadow-sm);
}

@media (max-width: 991px) {
  .create-post-card {
    display: none !important;
  }
  
  .floating-create-btn {
    display: flex;
  }
}

.btn-post {
  padding: 8px 24px;
  background: var(--primary-color);
  color: white;
  border: none;
  border-radius: 20px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  min-width: 80px;
  flex-shrink: 0;
}

.btn-post:hover:not(:disabled) {
  background: var(--primary-hover);
  transform: translateY(-1px);
}

.btn-post:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.post-textarea {
  width: 100%;
  min-height: 60px;
  max-height: 400px;
  border: none;
  outline: none;
  resize: none;
  font-size: 16px;
  line-height: 1.5;
  color: var(--text-primary);
  background: transparent;
  padding: 8px 0;
  overflow-y: auto;
  word-wrap: break-word;
  white-space: pre-wrap;
}

.post-textarea::placeholder {
  color: var(--text-secondary);
  opacity: 0.6;
}

.post-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--border-color);
}

.char-counter {
  font-size: 13px;
  color: var(--text-secondary);
  min-width: 60px;
  text-align: left;
}

.char-counter.warning {
  color: #f59e0b;
  font-weight: 600;
}

.char-counter.danger {
  color: #dc3545;
  font-weight: 600;
}

/* 글작성 모달 */
.create-post-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.create-post-modal.active {
  display: flex;
}

.modal-content-wrapper {
  background: var(--bg-primary);
  border-radius: 16px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  animation: modalSlideUp 0.3s ease;
}

@keyframes modalSlideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.modal-close-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
}

.modal-close-btn:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.modal-body {
  padding: 20px;
}

.modal-body .post-textarea {
  min-height: 120px;
  margin-bottom: 12px;
}

.modal-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

/* 새 글 알림 배너 */
.new-posts-banner {
  position: sticky;
  top: 60px;
  z-index: 100;
  margin-bottom: 16px;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.new-posts-banner button {
  width: 100%;
  padding: 12px 20px;
  background: var(--primary-color);
  color: white;
  border: none;
  border-radius: 12px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.new-posts-banner button:hover {
  background: var(--primary-hover);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.new-posts-banner button:active {
  transform: translateY(0);
}

.new-posts-banner svg {
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* 이미지 프리뷰 그리드 */
.image-preview-grid {
  display: grid;
  gap: 8px;
  margin-bottom: 12px;
  border-radius: 12px;
  overflow: hidden;
}
/* 1장 */
.image-preview-grid[data-count="1"] {
  grid-template-columns: 1fr;
}

.image-preview-grid[data-count="1"] .preview-item {
  aspect-ratio: 16/9;
}

/* 2장 */
.image-preview-grid[data-count="2"] {
  grid-template-columns: 1fr 1fr;
}

/* 3장 */
.image-preview-grid[data-count="3"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

.image-preview-grid[data-count="3"] .preview-item:first-child {
  grid-row: 1 / 3;
}

/* 4장 */
.image-preview-grid[data-count="4"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

/* 5~8장 */
.image-preview-grid[data-count="5"],
.image-preview-grid[data-count="6"],
.image-preview-grid[data-count="7"],
.image-preview-grid[data-count="8"] {
  grid-template-columns: repeat(3, 1fr);
}


/* 이미지 프리뷰 그리드 (최대 8개) */
.image-preview-grid {
  display: grid;
  gap: 8px;
  margin-bottom: 12px;
  border-radius: 12px;
  overflow: hidden;
}

/* 1장 */
.image-preview-grid[data-count="1"] {
  grid-template-columns: 1fr;
}

.image-preview-grid[data-count="1"] .preview-item {
  aspect-ratio: 16/9;
}

/* 2장 */
.image-preview-grid[data-count="2"] {
  grid-template-columns: 1fr 1fr;
}

/* 3장 */
.image-preview-grid[data-count="3"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

.image-preview-grid[data-count="3"] .preview-item:first-child {
  grid-row: 1 / 3;
}

/* 4장 */
.image-preview-grid[data-count="4"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

/* 5~8장 */
.image-preview-grid[data-count="5"],
.image-preview-grid[data-count="6"],
.image-preview-grid[data-count="7"],
.image-preview-grid[data-count="8"] {
  grid-template-columns: repeat(3, 1fr);
}

.image-preview-grid[data-count="5"] .preview-item:first-child,
.image-preview-grid[data-count="6"] .preview-item:first-child {
  grid-column: 1 / 3;
  grid-row: 1 / 3;
}
.preview-item {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--bg-secondary);
  border-radius: 8px;
  cursor: move;
}

.preview-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.preview-item .remove-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
  z-index: 10;
}

.preview-item:hover .remove-btn {
  opacity: 1;
}

.preview-item .remove-btn:hover {
  background: rgba(220, 53, 69, 0.9);
}

.preview-item .order-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  width: 24px;
  height: 24px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  opacity: 0;
  transition: opacity 0.2s;
}

.preview-item:hover .order-badge {
  opacity: 1;
}

.preview-item.dragging {
  opacity: 0.5;
}

.preview-item.drag-over {
  border: 2px dashed var(--primary-color);
}
.post-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}
.post-video::-webkit-media-controls {
  opacity: 0;
  transition: opacity 0.3s;
}

.post-video::-webkit-media-controls-enclosure {
  opacity: 0;
  transition: opacity 0.3s;
}

/* 호버 시 컨트롤 표시 */
.post-media-item:hover .post-video::-webkit-media-controls {
  opacity: 1;
}

.post-media-item:hover .post-video::-webkit-media-controls-enclosure {
  opacity: 1;
}

/* Firefox용 */
.post-video::-moz-media-controls {
  opacity: 0;
  transition: opacity 0.3s;
}

.post-media-item:hover .post-video::-moz-media-controls {
  opacity: 1;
}

/* 영상 컨테이너 호버 시 효과 */
.post-media-item[data-media-type="video"]:hover {
  cursor: pointer;
}

.post-media-item[data-media-type="video"]:hover .post-video {
  transform: scale(1.02);
}

/* 영상 재생 버튼 오버레이 (일시정지 상태에만 표시) */
.post-media-item[data-media-type="video"] .play-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 60px;
  height: 60px;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 50%;
  display: none;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
  pointer-events: none;
  z-index: 10;
  transition: all 0.3s;
}
.post-video.paused ~ .play-overlay {
  display: flex !important;
}

/* 미리보기 영상도 동일하게 적용 */
.preview-item[data-type="video"] video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.preview-item[data-type="video"] video::-webkit-media-controls {
  opacity: 0;
  transition: opacity 0.3s;
}

.preview-item[data-type="video"]:hover video::-webkit-media-controls {
  opacity: 1;
}

.preview-item[data-type="video"] video::-webkit-media-controls-enclosure {
  opacity: 0;
  transition: opacity 0.3s;
}

.preview-item[data-type="video"]:hover video::-webkit-media-controls-enclosure {
  opacity: 1;
}

/* 모바일에서는 터치 시 컨트롤 표시 */
@media (max-width: 768px) {
  .post-video::-webkit-media-controls {
    opacity: 0;
  }
  
  .post-video:active::-webkit-media-controls,
  .post-video:focus::-webkit-media-controls {
    opacity: 1;
  }
}

/* PIP 모드 버튼 스타일 개선 */
.post-video::-webkit-media-controls-picture-in-picture-button {
  display: block;
}

/* 볼륨 슬라이더 스타일 */
.post-video::-webkit-media-controls-volume-slider {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}
/* 게시물 이미지 그리드 */
.post-images-grid {
  display: grid;
  gap: 4px;
  margin-top: 12px;
  border-radius: 12px;
  overflow: hidden;
}

.post-images-grid[data-count="1"] {
  grid-template-columns: 1fr;
}

.post-images-grid[data-count="2"] {
  grid-template-columns: 1fr 1fr;
}

.post-images-grid[data-count="3"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

.post-images-grid[data-count="3"] .post-image-item:first-child {
  grid-row: 1 / 3;
}

.post-images-grid[data-count="4"] {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
}

.post-images-grid[data-count="5"],
.post-images-grid[data-count="6"],
.post-images-grid[data-count="7"],
.post-images-grid[data-count="8"] {
  grid-template-columns: repeat(3, 1fr);
}

.post-images-grid[data-count="5"] .post-image-item:first-child,
.post-images-grid[data-count="6"] .post-image-item:first-child {
  grid-column: 1 / 3;
  grid-row: 1 / 3;
}

.post-image-item {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  background: var(--bg-secondary);
  cursor: pointer;
}

.post-images-grid[data-count="1"] .post-image-item {
  aspect-ratio: 16/9;
  max-height: 500px;
}

.post-image-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.post-image-item:hover img {
  transform: scale(1.05);
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

/* 트위터 스타일 댓글 트리 */
.comment-wrapper {
  position: relative;
  margin-bottom: 0;
  padding-left: 0 !important;
}

.comment-wrapper.nested-comment {
  padding-left: 48px;
}

.comment-wrapper.nested-comment::before {
  content: '';
  position: absolute;
  left: 18px;
  top: -20px;
  width: 2px;
  height: calc(100% + 20px);
  background: var(--border-color);
  z-index: 0;
}

/* 첫 번째 답글의 연결선 시작 위치 조정 */
.comment-wrapper.nested-comment:first-of-type::before {
  top: -12px;
  height: calc(100% + 12px);
}

/* 마지막 답글의 연결선 끝 위치 조정 */
.comment-wrapper.nested-comment:last-of-type::before {
  height: 32px;
}

/* 답글 아이템 스타일 조정 */
.comment-wrapper.nested-comment .comment-item,
.comment-wrapper.nested-comment .comment-private {
  margin-left: -48px;
  padding-left: 48px;
  position: relative;
}

/* 답글의 프로필 이미지를 연결선 위에 표시 */
.comment-wrapper.nested-comment .comment-avatar {
  position: relative;
  z-index: 2;
  background: var(--bg-primary);
  border: 2px solid var(--bg-primary);
  box-sizing: content-box;
}

/* 답글 연결선의 가로선 추가 */
.comment-wrapper.nested-comment .comment-item::before {
  content: '';
  position: absolute;
  left: 18px;
  top: 50%;
  width: 22px;
  height: 2px;
  background: var(--border-color);
  z-index: 1;
}

/* 답글 입력 폼도 동일하게 처리 */
.reply-form {
  margin-top: 8px;
  margin-bottom: 8px;
  padding-left: 48px;
  position: relative;
}

.reply-form::before {
  content: '';
  position: absolute;
  left: 18px;
  top: 0;
  width: 2px;
  height: 24px;
  background: var(--border-color);
  z-index: 0;
}

.reply-form::after {
  content: '';
  position: absolute;
  left: 18px;
  top: 18px;
  width: 22px;
  height: 2px;
  background: var(--border-color);
  z-index: 0;
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
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.comment-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  flex-wrap: wrap;
  line-height: 1.4;
}

.comment-author {
  text-decoration: none;
  color: var(--text-primary);
  font-weight: 600;
}

.comment-author:hover {
  text-decoration: underline;
}

.comment-text {
  margin: 0 0 8px 0;
  font-size: 15px;
  line-height: 1.5;
  word-wrap: break-word;
}

.comment-actions {
  display: flex;
  gap: 16px;
  align-items: center;
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
  margin: 8px 0;
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

.comment-item {
  display: flex;
  gap: 12px;
  position: relative;
  padding: 8px 0;
  background: var(--bg-primary);
}

.replies-container {
  margin-top: 0;
  margin-left: 0;
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
  min-height: 60px;
  max-height: 200px;
  padding: 12px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  resize: none;
  font-size: 15px;
  line-height: 1.5;
  color: var(--text-primary);
  background: var(--bg-primary);
}

.comment-input-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.comment-char-counter {
  font-size: 13px;
  color: var(--text-secondary);
}

.like-btn.liked {
  color: #e0245e;
}

.sidebar-stats {
  display: flex;
  justify-content: space-around;
  padding: 12px 0;
  border-top: 1px solid var(--border-color);
}

.stat-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  color: var(--text-primary);
  transition: all 0.2s;
}

.stat-link:hover {
  transform: translateY(-2px);
  color: var(--primary-color);
}

.stat-link strong {
  font-size: 18px;
  display: block;
  margin-bottom: 2px;
}

.stat-link span {
  font-size: 13px;
  color: var(--text-secondary);
}

.auto-dismiss {
  animation: fadeOut 0.5s ease-in-out 3s forwards;
}

@keyframes fadeOut {
  0% { opacity: 1; }
  100% { opacity: 0; display: none; }
}
</style>

<script>

// 영상 재생/일시정지 토글
function toggleVideoPlay(video) {
  event.stopPropagation(); // 이벤트 버블링 방지
  
  if (video.paused) {
    video.play();
    video.classList.remove('paused');
    video.removeAttribute('data-user-paused'); // 사용자 일시정지 해제
  } else {
    video.pause();
    video.classList.add('paused');
    video.setAttribute('data-user-paused', 'true'); // 사용자가 일시정지함
  }
}

// 페이지 로드 시 모든 영상 자동 재생 설정
document.addEventListener('DOMContentLoaded', function() {
  const videos = document.querySelectorAll('.post-video');
  
  videos.forEach(video => {
    // 자동재생 속성 설정
    video.muted = true;
    video.autoplay = true;
    video.loop = true;
    video.playsInline = true;
    
    // 영상 로드 후 재생 시도
    video.addEventListener('loadeddata', function() {
      const playPromise = video.play();
      
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            video.classList.remove('paused');
          })
          .catch(error => {
            console.log('Autoplay prevented:', error);
            video.classList.add('paused');
          });
      }
    });
    
    // 일시정지/재생 이벤트
    video.addEventListener('pause', function() {
      video.classList.add('paused');
    });
    
    video.addEventListener('play', function() {
      video.classList.remove('paused');
    });
    
    // 음소거 해제 시 볼륨 조절 가능하도록
    video.addEventListener('volumechange', function() {
      if (!video.muted && video.volume > 0) {
        video.setAttribute('data-has-sound', 'true');
      }
    });
    
    // Intersection Observer
  const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      // 화면에 보이면 재생 (사용자가 일시정지하지 않은 경우만)
      if (video.paused && !video.hasAttribute('data-user-paused')) {
        video.play().catch(e => console.log('Play prevented:', e));
      }
    }
  });
}, {
  threshold: 0.5 // 50% 이상 보일 때
});
    
    observer.observe(video);
  });
});

// 영상 클릭 시 사용자가 의도적으로 일시정지했는지 표시
function toggleVideoPlay(video) {
  if (video.paused) {
    video.play();
    video.classList.remove('paused');
  } else {
    video.pause();
    video.classList.add('paused');
  }
}

// 미리보기 영상도 자동재생
function setupPreviewVideos() {
  const previewVideos = document.querySelectorAll('.preview-item video');
  
  previewVideos.forEach(video => {
    video.muted = true;
    video.autoplay = true;
    video.loop = true;
    video.playsInline = true;
    
    video.addEventListener('loadeddata', function() {
      video.play().catch(e => console.log('Preview play prevented:', e));
    });
  });
}

// ImageUploadManager의 render 함수에서 호출할 수 있도록
// render() 함수 마지막에 setupPreviewVideos() 추가 필요
function updateRelativeTimes() {
  // 게시물 시간 업데이트
  document.querySelectorAll('.post-relative-time').forEach(element => {
    const datetime = element.getAttribute('data-time');
    if (datetime) {
      element.textContent = getRelativeTime(datetime);
    }
  });
  
  // 댓글 시간 업데이트
  document.querySelectorAll('.comment-time').forEach(element => {
    const datetime = element.getAttribute('data-time');
    if (datetime) {
      element.textContent = getRelativeTime(datetime);
    }
  });
}

// JavaScript로 상대 시간 계산
function getRelativeTime(datetime) {
  const now = new Date();
  const past = new Date(datetime);
  const diffInSeconds = Math.floor((now - past) / 1000);
  
  if (diffInSeconds < 60) {
    return '방금';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return minutes + '분';
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600);
    return hours + '시간';
  } else if (diffInSeconds < 604800) {
    const days = Math.floor(diffInSeconds / 86400);
    return days + '일';
  } else {
    // 7일 이상이면 날짜 형식으로
    const month = past.getMonth() + 1;
    const day = past.getDate();
    return month + '월 ' + day + '일';
  }
}

// 페이지 로드 시 즉시 업데이트
updateRelativeTimes();

// 1분마다 시간 업데이트
setInterval(updateRelativeTimes, 60000);

// 이미지 관리 클래스
// 이미지 + 영상 관리 클래스
class ImageUploadManager {
  constructor(inputId, gridId, formId) {
    this.input = document.getElementById(inputId);
    this.grid = document.getElementById(gridId);
    this.form = document.getElementById(formId);
    this.images = [];
    this.maxImages = 8;
    
    if (this.input && this.grid) {
      this.init();
    }
  }
  
  init() {
    this.input.addEventListener('change', (e) => this.handleFileSelect(e));
    
    // 드래그 앤 드롭
    const dropZone = this.grid.closest('.create-post-card, .modal-body');
    if (dropZone) {
      dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
      });
      
      dropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
      });
      
      dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        this.handleDrop(e);
      });
    }
    
    // 붙여넣기
    const textarea = this.form.querySelector('textarea[name="content"]');
    if (textarea) {
      textarea.addEventListener('paste', (e) => this.handlePaste(e));
    }
  }
  
  handleFileSelect(e) {
    const files = Array.from(e.target.files);
    this.addImages(files);
  }
  
  handleDrop(e) {
    const files = Array.from(e.dataTransfer.files).filter(f => 
      f.type.startsWith('image/') || f.type.startsWith('video/')
    );
    this.addImages(files);
  }
  
  handlePaste(e) {
    const items = Array.from(e.clipboardData.items);
    const mediaFiles = items
      .filter(item => item.type.startsWith('image/') || item.type.startsWith('video/'))
      .map(item => item.getAsFile())
      .filter(file => file !== null);
    
    if (mediaFiles.length > 0) {
      e.preventDefault();
      this.addImages(mediaFiles);
    }
  }
  
  addImages(files) {
    const remaining = this.maxImages - this.images.length;
    
    if (remaining <= 0) {
      alert(`최대 ${this.maxImages}개까지 업로드 가능합니다.`);
      return;
    }
    
    const filesToAdd = files.slice(0, remaining);
    
    filesToAdd.forEach(file => {
      // 영상은 50MB, 이미지는 5MB 제한
      const maxSize = file.type.startsWith('video/') ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
      const sizeLimit = file.type.startsWith('video/') ? '50MB' : '5MB';
      
      if (file.size > maxSize) {
        alert(`${file.name}의 크기가 ${sizeLimit}를 초과합니다.`);
        return;
      }
      
      const reader = new FileReader();
      reader.onload = (e) => {
        this.images.push({
          file: file,
          dataUrl: e.target.result,
          id: Date.now() + Math.random(),
          type: file.type.startsWith('video/') ? 'video' : 'image'
        });
        this.render();
      };
      reader.readAsDataURL(file);
    });
    
    if (files.length > remaining) {
      alert(`${remaining}개만 추가되었습니다. (최대 ${this.maxImages}개)`);
    }
  }
  
  removeImage(index) {
    this.images.splice(index, 1);
    this.render();
  }
  
  moveImage(fromIndex, toIndex) {
    const [image] = this.images.splice(fromIndex, 1);
    this.images.splice(toIndex, 0, image);
    this.render();
  }
  
  render() {
  if (this.images.length === 0) {
    this.grid.style.display = 'none';
    this.grid.innerHTML = '';
    this.updateFormInput();
    return;
  }
  
  this.grid.style.display = 'grid';
  this.grid.setAttribute('data-count', this.images.length);
  
  this.grid.innerHTML = this.images.map((img, index) => `
    <div class="preview-item" 
         draggable="true" 
         data-index="${index}"
         data-id="${img.id}"
         data-type="${img.type}">
      ${img.type === 'video' ? 
        `<video src="${img.dataUrl}" preload="metadata" autoplay loop muted playsinline></video>` : 
        `<img src="${img.dataUrl}" alt="preview">`
      }
      <button type="button" class="remove-btn" onclick="imageManager${this.getManagerId()}.removeImage(${index})">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
      <div class="order-badge">${index + 1}</div>
      ${img.type === 'video' ? '<div class="media-type-badge">영상</div>' : ''}
    </div>
  `).join('');
  
  // 드래그 이벤트 리스너 추가
  this.grid.querySelectorAll('.preview-item').forEach(item => {
    item.addEventListener('dragstart', (e) => this.handleDragStart(e));
    item.addEventListener('dragend', (e) => this.handleDragEnd(e));
    item.addEventListener('dragover', (e) => this.handleDragOver(e));
    item.addEventListener('drop', (e) => this.handleImageDrop(e));
  });
  
  // 미리보기 영상 자동재생 설정
  this.grid.querySelectorAll('video').forEach(video => {
    video.play().catch(e => console.log('Preview play prevented:', e));
  });
  
  this.updateFormInput();
}
  
  handleDragStart(e) {
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', e.target.dataset.index);
  }
  
  handleDragEnd(e) {
    e.target.classList.remove('dragging');
    this.grid.querySelectorAll('.preview-item').forEach(item => {
      item.classList.remove('drag-over');
    });
  }
  
  handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    
    const dragging = this.grid.querySelector('.dragging');
    const target = e.target.closest('.preview-item');
    
    if (target && target !== dragging) {
      target.classList.add('drag-over');
    }
  }
  
  handleImageDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
    const target = e.target.closest('.preview-item');
    
    if (target) {
      const toIndex = parseInt(target.dataset.index);
      this.moveImage(fromIndex, toIndex);
    }
  }
  
  updateFormInput() {
    // DataTransfer 객체로 파일 순서 재구성
    const dataTransfer = new DataTransfer();
    this.images.forEach(img => {
      dataTransfer.items.add(img.file);
    });
    
    this.input.files = dataTransfer.files;
  }
  
  getManagerId() {
    return this.input.id === 'imageInput' ? 'Main' : 'Modal';
  }
  
  reset() {
    this.images = [];
    this.input.value = '';
    this.render();
  }
}

// 메인 폼 이미지 매니저
let imageManagerMain;
if (document.getElementById('imageInput')) {
  imageManagerMain = new ImageUploadManager('imageInput', 'imagePreviewGrid', 'mainPostForm');
}

// 모달 폼 이미지 매니저
let imageManagerModal;
if (document.getElementById('modalImageInput')) {
  imageManagerModal = new ImageUploadManager('modalImageInput', 'modalImagePreviewGrid', 'modalPostForm');
}
// 이미지 모달 열기
function openImageModal(src) {
  const modal = document.createElement('div');
  modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:10000;display:flex;align-items:center;justify-content:center;cursor:pointer;';
  modal.onclick = () => modal.remove();
  
  const img = document.createElement('img');
  img.src = src;
  img.style.cssText = 'max-width:90%;max-height:90%;object-fit:contain;';
  
  modal.appendChild(img);
  document.body.appendChild(modal);
}

// 모달 열기/닫기
function openCreateModal() {
  document.getElementById('createModal').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeCreateModal() {
  document.getElementById('createModal')?.classList.remove('active');
  document.body.style.overflow = '';
  
  const form = document.getElementById('modalPostForm');
  form?.reset();
  
  if (imageManagerModal) {
    imageManagerModal.reset();
  }
}

// 모달 외부 클릭 시 닫기
document.getElementById('createModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeCreateModal();
  }
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeCreateModal();
  }
});

// 새 글 체크 관련
let latestPostId = <?php echo $posts ? $posts[0]['post_id'] : 0; ?>;
let checkInterval;
let isPageVisible = true;

// 페이지 가시성 감지
document.addEventListener('visibilitychange', function() {
  isPageVisible = !document.hidden;
  
  if (isPageVisible && latestPostId > 0) {
    checkNewPosts();
  }
});

// 새 글 체크 함수
function checkNewPosts() {
  if (!isPageVisible || latestPostId === 0) return;
  
  fetch('<?php echo BASE_URL; ?>/api/check_new_posts.php?latest_id=' + latestPostId)
    .then(res => res.json())
    .then(data => {
      if (data.success && data.new_posts > 0) {
        showNewPostsBanner(data.new_posts);
      }
    })
    .catch(err => console.error('Check new posts error:', err));
}

// 새 글 배너 표시
function showNewPostsBanner(count) {
  const banner = document.getElementById('newPostsBanner');
  const countSpan = document.getElementById('newPostsCount');
  
  if (banner && countSpan) {
    countSpan.textContent = count;
    banner.style.display = 'block';
  }
}

// 새 글 로드
function loadNewPosts() {
  location.reload();
}

// 15초마다 새 글 체크
if (latestPostId > 0) {
  checkInterval = setInterval(checkNewPosts, 15000);
  setTimeout(checkNewPosts, 5000);
}

// 페이지 언로드 시 인터벌 정리
window.addEventListener('beforeunload', function() {
  if (checkInterval) {
    clearInterval(checkInterval);
  }
});
// 게시물 클릭 시 상세 페이지로 이동
function goToPostDetail(postId, event) {
  // 버튼, 링크 클릭 시에는 이동하지 않음
  if (event.target.closest('.post-action-btn, .post-menu-btn, a, button, video')) {
    return;
  }
  window.location.href = '<?php echo BASE_URL; ?>/pages/post_detail.php?id=' + postId;
}


// 좋아요 토글
function toggleLike(postId) {
  fetch('<?php echo BASE_URL; ?>/api/like_toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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

// 댓글 섹션 토글
function toggleComments(postId) {
  const commentsSection = document.getElementById('comments-' + postId);
  if (commentsSection.style.display === 'none') {
    commentsSection.style.display = 'block';
  } else {
    commentsSection.style.display = 'none';
  }
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
    sessionStorage.setItem('openComments', postId);
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
    location.reload();
  })
  .catch(err => {
    console.error('Delete error:', err);
    alert('삭제에 실패했습니다.');
  });
}

function autoResizeTextarea(textarea) {
  textarea.style.height = 'auto';
  textarea.style.height = Math.min(textarea.scrollHeight, 400) + 'px';
}

// 모든 post-textarea에 이벤트 리스너 추가
document.addEventListener('DOMContentLoaded', function() {
  const textareas = document.querySelectorAll('.post-textarea');
  
  textareas.forEach(textarea => {
    // 글자수 제한
    textarea.setAttribute('maxlength', '1000');
    
    // 입력 시 자동 높이 조절
    textarea.addEventListener('input', function() {
      autoResizeTextarea(this);
      
      // 글자수 표시 (선택사항)
      const remaining = 1000 - this.value.length;
      const counter = this.closest('form').querySelector('.char-counter');
      if (counter) {
        counter.textContent = `${remaining}자 남음`;
      }
    });
    
    // 붙여넣기 시에도 조절
    textarea.addEventListener('paste', function() {
      setTimeout(() => autoResizeTextarea(this), 0);
    });
  });
});
document.addEventListener('DOMContentLoaded', function() {
  // 모든 게시물 영상 찾기
  const videos = document.querySelectorAll('.post-video');
  
  videos.forEach(video => {
    // 자동재생 속성 설정
    video.muted = true; // 음소거 (자동재생을 위해 필수)
    video.autoplay = true;
    video.loop = true;
    video.playsInline = true;
    
    // 영상 로드 후 재생 시도
    video.addEventListener('loadeddata', function() {
      const playPromise = video.play();
      
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            // 재생 성공
            video.classList.remove('paused');
          })
          .catch(error => {
            // 자동재생 실패 (브라우저 정책)
            console.log('Autoplay prevented:', error);
            video.classList.add('paused');
          });
      }
    });
    
    // 일시정지 이벤트 리스너
    video.addEventListener('pause', function() {
      video.classList.add('paused');
    });
    
    // 재생 이벤트 리스너
    video.addEventListener('play', function() {
      video.classList.remove('paused');
    });
    
    // Intersection Observer로 화면에 보일 때만 재생
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // 화면에 보이면 재생
          if (video.paused && !video.classList.contains('user-paused')) {
            video.play().catch(e => console.log('Play prevented:', e));
          }
        } else {
          // 화면 밖으로 나가면 일시정지 (성능 최적화)
          // video.pause(); // 원하면 주석 해제
        }
      });
    }, {
      threshold: 0.5 // 50% 이상 보일 때
    });
    
    observer.observe(video);
  });
});

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
    sessionStorage.setItem('openComments', postId);
    location.reload();
  })
  .catch(err => {
    console.error('Delete error:', err);
    alert('삭제에 실패했습니다.');
  });
}

// 더보기 버튼
function loadMoreComments(postId, parentId) {
  const hiddenComments = document.querySelectorAll(`#comments-${postId} .hidden-comment`);
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

// 페이지 로드 시 댓글 섹션 상태 복원
window.addEventListener('load', function() {
  const openComments = sessionStorage.getItem('openComments');
  if (openComments) {
    const commentsSection = document.getElementById('comments-' + openComments);
    if (commentsSection) {
      commentsSection.style.display = 'block';
      commentsSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    sessionStorage.removeItem('openComments');
  }
});

// 자동 사라지는 알림
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.auto-dismiss');
  alerts.forEach(alert => {
    setTimeout(() => alert.remove(), 3500);
  });
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>