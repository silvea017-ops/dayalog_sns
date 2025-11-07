<?php
// public/pages/following.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/notifications.php';
require_once FUNCTIONS_PATH . '/comment_privacy.php';
require_once FUNCTIONS_PATH . '/block.php';
require_once FUNCTIONS_PATH . '/date_helper.php';
require_once INCLUDES_PATH . '/header.php';

$current_user_id = $_SESSION['user']['user_id'];

// getRelativeTime() 함수는 date_helper.php에 정의되어 있으므로 여기서 선언하지 않음

// 재귀 함수로 댓글 트리 렌더링 (index.php와 동일)
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

// 팔로우 중인 사용자의 게시물 + 내 게시물 가져오기 (차단된 사용자 제외)
$stmt = $pdo->prepare("
    SELECT p.*, u.nickname, u.username, u.profile_img, u.user_id, u.is_private
    FROM posts p 
    JOIN users u ON p.user_id = u.user_id 
    WHERE (
        p.user_id = ? 
        OR EXISTS (
            SELECT 1 FROM follows f 
            WHERE f.follower_id = ? 
            AND f.following_id = p.user_id 
            AND f.status = 'accepted'
        )
    )
    AND p.user_id NOT IN (
        SELECT blocked_id FROM blocks WHERE blocker_id = ?
        UNION
        SELECT blocker_id FROM blocks WHERE blocked_id = ?
    )
    ORDER BY p.created_at DESC
");
$stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id]);
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
        <h4 class="mb-0">팔로우 중인 사용자 게시물</h4>
      </div>

      <?php if(empty($posts)): ?>
        <div class="empty-state">
          <i class="fa-solid fa-lock" style="font-size: 48px; opacity: 0.5;"></i>
          <p>팔로우 중인 사용자의 게시물이 없습니다</p>
          <small>다른 사용자를 팔로우하고 게시물을 확인하세요!</small>
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
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $stmt->execute([$post['post_id']]);
        $like_count = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
        $stmt->execute([$post['post_id']]);
        $comment_count = $stmt->fetch()['count'];
        
        $user_liked = false;
        $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$current_user_id, $post['post_id']]);
        $user_liked = $stmt->fetch() ? true : false;
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
  <i class="fa-solid fa-lock" style="font-size: 12px; flex-shrink: 0;"></i>
<?php endif; ?>
                  <span class="text-muted">@<?php echo htmlspecialchars($post['username']); ?></span>
                  <span class="text-muted">·</span>
                  <span class="text-muted" data-time="<?php echo $post['created_at']; ?>">
                    <?php echo getRelativeTime($post['created_at']); ?>
                  </span>
                </div>
              </div>
            </div>
            
            <?php if($_SESSION['user']['user_id'] === $post['user_id']): ?>
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
            <button class="post-action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>" 
                    onclick="toggleLike(<?php echo $post['post_id']; ?>, event)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $user_liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
              좋아요
            </button>
            
            <button class="post-action-btn" onclick="toggleComments(<?php echo $post['post_id']; ?>, event)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
              댓글
            </button>
            
            <button class="post-action-btn" onclick="sharePost(<?php echo $post['post_id']; ?>, event)">
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
          </div>
        </div>
      <?php endforeach; ?>

    </div>

    <!-- 사이드바 -->
    <div class="col-lg-4 d-none d-lg-block">
      <div class="sidebar-card sticky-top">
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
      </div>
    </div>
  </div>
</div>

<script>
// 영상 재생/일시정지 토글
function toggleVideoPlay(video) {
  event.stopPropagation();
  
  if (video.paused) {
    video.play();
    video.classList.remove('paused');
    video.removeAttribute('data-user-paused');
  } else {
    video.pause();
    video.classList.add('paused');
    video.setAttribute('data-user-paused', 'true');
  }
}

// 페이지 로드 시 모든 영상 자동 재생 설정
document.addEventListener('DOMContentLoaded', function() {
  const videos = document.querySelectorAll('.post-video');
  
  videos.forEach(video => {
    video.muted = true;
    video.autoplay = true;
    video.loop = true;
    video.playsInline = true;
    
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
    
    video.addEventListener('pause', function() {
      video.classList.add('paused');
    });
    
    video.addEventListener('play', function() {
      video.classList.remove('paused');
    });
    
    video.addEventListener('volumechange', function() {
      if (!video.muted && video.volume > 0) {
        video.setAttribute('data-has-sound', 'true');
      }
    });
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          if (video.paused && !video.hasAttribute('data-user-paused')) {
            video.play().catch(e => console.log('Play prevented:', e));
          }
        }
      });
    }, {
      threshold: 0.5
    });
    
    observer.observe(video);
  });
});

function updateRelativeTimes() {
  document.querySelectorAll('[data-time]').forEach(element => {
    const datetime = element.getAttribute('data-time');
    if (datetime) {
      element.textContent = getRelativeTime(datetime);
    }
  });
}

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
    const month = past.getMonth() + 1;
    const day = past.getDate();
    return month + '월 ' + day + '일';
  }
}

updateRelativeTimes();
setInterval(updateRelativeTimes, 60000);

// 이미지 관리 클래스
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
    
    this.grid.querySelectorAll('.preview-item').forEach(item => {
      item.addEventListener('dragstart', (e) => this.handleDragStart(e));
      item.addEventListener('dragend', (e) => this.handleDragEnd(e));
      item.addEventListener('dragover', (e) => this.handleDragOver(e));
      item.addEventListener('drop', (e) => this.handleImageDrop(e));
    });
    
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

let imageManagerMain;
if (document.getElementById('imageInput')) {
  imageManagerMain = new ImageUploadManager('imageInput', 'imagePreviewGrid', 'mainPostForm');
}

let imageManagerModal;
if (document.getElementById('modalImageInput')) {
  imageManagerModal = new ImageUploadManager('modalImageInput', 'modalImagePreviewGrid', 'modalPostForm');
}

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

document.getElementById('createModal')?.addEventListener('click', function(e) {
  if (e.target === this) {
    closeCreateModal();
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeCreateModal();
  }
});

// 새 글 체크 관련 (팔로잉 전용)
let latestPostId = <?php echo $posts ? $posts[0]['post_id'] : 0; ?>;
let checkInterval;
let isPageVisible = true;

document.addEventListener('visibilitychange', function() {
  isPageVisible = !document.hidden;
  
  if (isPageVisible && latestPostId > 0) {
    checkNewPosts();
  }
});

function checkNewPosts() {
  if (!isPageVisible || latestPostId === 0) return;
  
  // following=1 파라미터로 팔로잉 피드임을 명시
  fetch('<?php echo BASE_URL; ?>/api/check_new_posts.php?latest_id=' + latestPostId + '&following=1')
    .then(res => res.json())
    .then(data => {
      if (data.success && data.new_posts > 0) {
        showNewPostsBanner(data.new_posts);
      }
    })
    .catch(err => console.error('Check new posts error:', err));
}

function showNewPostsBanner(count) {
  const banner = document.getElementById('newPostsBanner');
  const countSpan = document.getElementById('newPostsCount');
  
  if (banner && countSpan) {
    countSpan.textContent = count;
    banner.style.display = 'block';
  }
}

function loadNewPosts() {
  location.reload();
}

if (latestPostId > 0) {
  checkInterval = setInterval(checkNewPosts, 15000);
  setTimeout(checkNewPosts, 5000);
}

window.addEventListener('beforeunload', function() {
  if (checkInterval) {
    clearInterval(checkInterval);
  }
});

// 게시물 클릭 시 상세 페이지로 이동
function goToPostDetail(postId, event) {
  if (event.target.closest('.post-action-btn, .post-menu-btn, .comments-section, a, button, video, .dropdown')) {
    return;
  }
  window.location.href = '<?php echo BASE_URL; ?>/pages/post_detail.php?id=' + postId;
}

function toggleComments(postId, event) {
  if (event) {
    event.stopPropagation();
  }
  
  const commentsSection = document.getElementById('comments-' + postId);
  if (commentsSection.style.display === 'none') {
    commentsSection.style.display = 'block';
  } else {
    commentsSection.style.display = 'none';
  }
}

function toggleLike(postId, event) {
  if (event) {
    event.stopPropagation();
  }
  
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

function hideReplyForm(commentId) {
  const replyForm = document.getElementById('reply-form-' + commentId);
  if (replyForm) {
    replyForm.style.display = 'none';
    const input = replyForm.querySelector('input[name="content"]');
    input.value = '';
  }
}

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

function loadMoreComments(postId, parentId) {
  const hiddenComments = document.querySelectorAll(`#comments-${postId} .hidden-comment`);
  hiddenComments.forEach(comment => {
    comment.style.display = 'block';
    comment.classList.remove('hidden-comment');
  });
  
  event.target.style.display = 'none';
}

function sharePost(postId, event) {
  if (event) {
    event.stopPropagation();
  }
  
  const url = window.location.origin + '<?php echo BASE_URL; ?>/pages/post_detail.php?id=' + postId;
  
  if (navigator.share) {
    navigator.share({ title: '게시물 공유', url: url });
  } else {
    navigator.clipboard.writeText(url).then(() => {
      alert('링크가 복사되었습니다!');
    });
  }
}

function autoResizeTextarea(textarea) {
  textarea.style.height = 'auto';
  textarea.style.height = Math.min(textarea.scrollHeight, 400) + 'px';
}

document.addEventListener('DOMContentLoaded', function() {
  const textareas = document.querySelectorAll('.post-textarea');
  
  textareas.forEach(textarea => {
    textarea.setAttribute('maxlength', '1000');
    
    textarea.addEventListener('input', function() {
      autoResizeTextarea(this);
      
      const remaining = 1000 - this.value.length;
      const counter = this.closest('form').querySelector('.char-counter');
      if (counter) {
        counter.textContent = `${remaining}자 남음`;
      }
    });
    
    textarea.addEventListener('paste', function() {
      setTimeout(() => autoResizeTextarea(this), 0);
    });
  });
});

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

document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.auto-dismiss');
  alerts.forEach(alert => {
    setTimeout(() => alert.remove(), 3500);
  });
});
// 댓글 입력 시 글자수 카운터 업데이트
document.addEventListener('DOMContentLoaded', function() {
  // 모든 댓글 입력창에 이벤트 리스너 추가
  function setupCommentInput(textarea) {
    const container = textarea.closest('.comment-input-container');
    const counter = container?.querySelector('.comment-char-counter');
    
    if (!counter) return;
    
    textarea.addEventListener('input', function() {
      const remaining = 1000 - this.value.length;
      counter.textContent = `${remaining}자 남음`;
      
      if (remaining < 100) {
        counter.classList.add('warning');
      } else {
        counter.classList.remove('warning');
      }
      
      if (remaining < 0) {
        counter.classList.add('danger');
      } else {
        counter.classList.remove('danger');
      }
    });
  }
  
  // 페이지 로드 시 모든 댓글 입력창에 적용
  document.querySelectorAll('.comment-input').forEach(setupCommentInput);
  
  // 답글 폼이 동적으로 생성될 때를 대비한 MutationObserver
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      mutation.addedNodes.forEach(function(node) {
        if (node.nodeType === 1) {
          const inputs = node.querySelectorAll ? node.querySelectorAll('.comment-input, input[name="content"]') : [];
          inputs.forEach(input => {
            if (input.classList.contains('comment-input')) {
              setupCommentInput(input);
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