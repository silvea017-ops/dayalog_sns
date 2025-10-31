<?php
// user_profile.php (차단 기능 추가)
require_once __DIR__ . '/../../config/db.php';
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once FUNCTIONS_PATH . '/block.php';
require_once __DIR__ . '/../../includes/header.php';

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: index.php');
    exit;
}

// 사용자 정보
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// 본인 프로필인지 확인
$is_own_profile = isset($_SESSION['user']) && $_SESSION['user']['user_id'] == $user_id;

// 차단 여부 확인
$is_blocked_by_me = false;
$is_blocked_me = false;
if (isset($_SESSION['user']) && !$is_own_profile) {
    $current_user_id = $_SESSION['user']['user_id'];
    $is_blocked_by_me = isBlocked($pdo, $current_user_id, $user_id);
    $is_blocked_me = isBlocked($pdo, $user_id, $current_user_id);
}

// 차단된 경우 접근 차단
if ($is_blocked_me && !$is_own_profile) {
    require_once INCLUDES_PATH . '/header.php';
    ?>
    <div class="container mt-4">
      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="blocked-notice">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
            </svg>
            <h4>이 사용자의 게시글을 볼 수 없습니다</h4>
            <p class="text-muted">계정 소유자가 회원님의 접근을 차단했습니다.</p>
            <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn btn-primary mt-3">홈으로 돌아가기</a>
          </div>
        </div>
      </div>
    </div>
    <style>
    .blocked-notice {
      text-align: center;
      padding: 80px 20px;
      background: var(--bg-primary);
      border-radius: 12px;
      border: 1px solid var(--border-color);
    }
    .blocked-notice svg {
      margin-bottom: 24px;
      opacity: 0.5;
      color: var(--text-secondary);
    }
    .blocked-notice h4 {
      margin-bottom: 12px;
    }
    </style>
    <?php
    require_once INCLUDES_PATH . '/footer.php';
    exit;
}

// 팔로우 상태 확인
$is_following = false;
$follow_status = null;
if (isset($_SESSION['user']) && !$is_own_profile && !$is_blocked_by_me && !$is_blocked_me) {
    $stmt = $pdo->prepare("SELECT status FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$_SESSION['user']['user_id'], $user_id]);
    $follow_record = $stmt->fetch();
    if ($follow_record) {
        $follow_status = $follow_record['status'];
        $is_following = ($follow_status === 'accepted');
    }
}


// DM 전송 가능 여부 확인 (이 부분을 추가)
$can_send_dm = false;
if (isset($_SESSION['user']) && !$is_own_profile && !$is_blocked_by_me && !$is_blocked_me) {
    $current_user_id = $_SESSION['user']['user_id'];
    
    // 상대방의 DM 권한 설정 확인
    if ($user['dm_permission'] === 'everyone') {
        $can_send_dm = true;
    } elseif ($user['dm_permission'] === 'followers') {
        // 서로 팔로우 중인지 확인
        $stmt = $pdo->prepare("
            SELECT 
                EXISTS (
                    SELECT 1 FROM follows 
                    WHERE follower_id = ? 
                    AND following_id = ? 
                    AND status = 'accepted'
                ) as is_following,
                EXISTS (
                    SELECT 1 FROM follows 
                    WHERE follower_id = ? 
                    AND following_id = ? 
                    AND status = 'accepted'
                ) as is_follower
        ");
        $stmt->execute([$current_user_id, $user_id, $user_id, $current_user_id]);
        $mutual = $stmt->fetch();
        $can_send_dm = $mutual['is_following'] && $mutual['is_follower'];
    }
}

// 비공개 계정이고 팔로워가 아닌 경우 콘텐츠 제한
$can_view_content = $is_own_profile || !$user['is_private'] || $is_following;


// 모든 커버 이미지 가져오기
$stmt = $pdo->prepare("SELECT image_path FROM cover_images WHERE user_id = ? ORDER BY display_order ASC, created_at DESC");
$stmt->execute([$user_id]);
$cover_images = $stmt->fetchAll();

// 활성화된 커버 이미지
$stmt = $pdo->prepare("SELECT image_path FROM cover_images WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$user_id]);
$active_cover = $stmt->fetch();
$cover_image = $active_cover ? $active_cover['image_path'] : ($user['cover_img'] ?? null);

// 게시물 수
$post_count = 0;
if ($can_view_content) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM posts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $post_count = $stmt->fetch()['count'];
}

// 팔로워 수
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM follows WHERE following_id = ? AND status = 'accepted'");
$stmt->execute([$user_id]);
$follower_count = $stmt->fetch()['count'];

// 팔로잉 수
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ? AND status = 'accepted'");
$stmt->execute([$user_id]);
$following_count = $stmt->fetch()['count'];

// 게시물 가져오기
$posts = [];
if ($can_view_content) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $posts = $stmt->fetchAll();
}
?>

<!-- 프로필 헤더 부분은 기존과 동일 -->
<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
      
      <!-- 프로필 헤더 카드 -->
      <div class="profile-header-card mb-4">
        <?php if(count($cover_images) > 1): ?>
          <!-- Swiper 커버 이미지 -->
          <div class="profile-cover profile-cover-swiper">
            <div class="swiper-container" id="profileHeaderSwiper">
              <div class="swiper-wrapper">
                <?php foreach($cover_images as $cover): ?>
                <div class="swiper-slide">
                  <div class="profile-cover-slide" style="background-image: url('<?php echo '../'.htmlspecialchars($cover['image_path']); ?>');"></div>
                </div>
                <?php endforeach; ?>
              </div>
              <div class="swiper-button-prev"></div>
              <div class="swiper-button-next"></div>
              <div class="swiper-pagination"></div>
            </div>
          </div>
        <?php elseif(count($cover_images) === 1): ?>
          <div class="profile-cover" style="background-image: url('<?php echo '../'.htmlspecialchars($cover_images[0]['image_path']); ?>'); background-size: cover; background-position: center;"></div>
        <?php else: ?>
          <div class="profile-cover" style="<?php if($cover_image): ?>background-image: url('<?php echo '../'.htmlspecialchars($cover_image); ?>'); background-size: cover; background-position: center;<?php endif; ?>"></div>
        <?php endif; ?>

        <div class="profile-info">
          <div class="profile-avatar">
            <img src="<?php echo $user['profile_img'] ? '../'.htmlspecialchars($user['profile_img']) : '../assets/images/sample.png'; ?>" 
                 alt="profile">
          </div>
          <div class="profile-details">
            <h2 class="profile-name">
              <?php echo htmlspecialchars($user['nickname']); ?>
              <?php if($user['is_private']): ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-2" style="vertical-align: middle;">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              <?php endif; ?>
            </h2>
            <p class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></p>
            <?php if($user['bio']): ?>
              <p class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            <?php endif; ?>
            <div class="profile-stats">
              <div class="stat-item">
                <strong><?php echo $post_count; ?></strong>
                <span>게시물</span>
              </div>
              <a href="follows.php?id=<?php echo $user_id; ?>&type=followers" class="stat-item stat-link">
                <strong><?php echo $follower_count; ?></strong>
                <span>팔로워</span>
              </a>
              <a href="follows.php?id=<?php echo $user_id; ?>&type=following" class="stat-item stat-link">
                <strong><?php echo $following_count; ?></strong>
                <span>팔로잉</span>
              </a>
            </div>
            
            <?php if($is_own_profile): ?>
              <a href="profile.php" class="btn-edit-profile">프로필 편집</a>
            <?php elseif(isset($_SESSION['user'])): ?>
              <div class="profile-actions">
                <?php if($is_blocked_by_me): ?>
                  <button class="btn-blocked" onclick="unblockUser(<?php echo $user_id; ?>, '<?php echo htmlspecialchars($user['nickname']); ?>')">
                    차단됨
                  </button>
                  <?php else: ?>
                  <!-- 팔로우 버튼 -->
                  <form method="post" action="<?php echo BASE_URL; ?>/api/follow_toggle.php" style="display: inline;">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="redirect" value="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $user_id; ?>">
                    <?php if($follow_status === 'pending'): ?>
                      <button type="submit" class="btn-pending">요청됨</button>
                    <?php else: ?>
                      <button type="submit" class="<?php echo $is_following ? 'btn-following' : 'btn-follow'; ?>">
                        <?php echo $is_following ? '팔로잉' : '팔로우'; ?>
                      </button>
                    <?php endif; ?>
                  </form>
                  
                  <!-- DM 버튼 -->
                  <?php if($can_send_dm): ?>
                    <button class="btn-dm" onclick="startDirectMessage(<?php echo $user_id; ?>)" title="메시지 보내기">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                      </svg>
                    </button>
                  <?php else: ?>
                    <button class="btn-dm disabled" disabled title="메시지를 보낼 수 없습니다">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                      </svg>
                    </button>
                  <?php endif; ?>
                  
                  <!-- 더보기 메뉴 -->
                  <div class="dropdown d-inline-block ms-2">
                    <button class="btn-profile-menu" type="button" data-bs-toggle="dropdown">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="12" cy="5" r="1"></circle>
                        <circle cx="12" cy="19" r="1"></circle>
                      </svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li>
                        <button class="dropdown-item text-danger" onclick="blockUser(<?php echo $user_id; ?>, '<?php echo htmlspecialchars($user['nickname']); ?>')">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                          </svg>
                          차단
                        </button>
                      </li>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <a href="login.php" class="btn-follow">팔로우</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if($is_blocked_by_me): ?>
        <!-- 차단된 사용자 메시지 -->
        <div class="blocked-content-notice">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
          </svg>
          <p>차단한 사용자입니다</p>
          <button class="btn btn-sm btn-outline-secondary" onclick="unblockUser(<?php echo $user_id; ?>, '<?php echo htmlspecialchars($user['nickname']); ?>')">
            차단 해제
          </button>
        </div>
      <?php elseif(!$can_view_content): ?>
        <!-- 비공개 계정 메시지 -->
        <div class="private-account-notice">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <h4>비공개 계정입니다</h4>
          <p><?php echo htmlspecialchars($user['nickname']); ?>님을 팔로우하여 게시물을 확인하세요.</p>
          <?php if($follow_status === 'pending'): ?>
            <p class="text-muted"><small>팔로우 요청이 승인 대기 중입니다.</small></p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <!-- 탭 네비게이션 -->
        <div class="profile-tabs mb-4">
          <button class="tab-btn active" data-tab="posts">게시물</button>
          <button class="tab-btn" data-tab="media">미디어</button>
          <button class="tab-btn" data-tab="likes">좋아요</button>
        </div>

        <!-- 게시물 탭 -->
        <div id="posts-tab" class="tab-content active">
          <?php if(empty($posts)): ?>
            <div class="empty-state">
              <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
              </svg>
              <p>아직 게시물이 없습니다</p>
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

$post_user_liked = false;
if (isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$_SESSION['user']['user_id'], $post['post_id']]);
    $post_user_liked = $stmt->fetch() ? true : false;
}
?>
      <div class="post-card mb-4" onclick="goToPostDetail(<?php echo $post['post_id']; ?>, event)" style="cursor: pointer;">
            <div class="post-header">
              <div class="d-flex align-items-center gap-3">
                <img src="<?php echo $user['profile_img'] ? '../'.htmlspecialchars($user['profile_img']) : '../assets/images/sample.png'; ?>" 
                     class="profile-img-sm" alt="profile">
                <div class="flex-grow-1">
                  <strong class="post-author"><?php echo htmlspecialchars($user['nickname']); ?></strong>
                  <div class="post-time"><?php echo htmlspecialchars($post['created_at']); ?></div>
                </div>
                
                <?php if($is_own_profile): ?>
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
                src="<?php echo '../' . htmlspecialchars($media['image_path']); ?>" 
                controls
                preload="metadata"
                onclick="event.stopPropagation()">
              </video>
            <?php else: ?>
              <img 
                src="<?php echo '../' . htmlspecialchars($media['image_path']); ?>" 
                alt="post media"
                onclick="event.stopPropagation()">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
            <div class="post-footer">
              <button class="post-action-btn <?php echo $post_user_liked ? 'liked' : ''; ?>" 
                      onclick="toggleLike(<?php echo $post['post_id']; ?>, this)"
                      data-post-id="<?php echo $post['post_id']; ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $post_user_liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <span class="like-count"><?php echo $like_count > 0 ? $like_count : ''; ?></span>
              </button>
              
              <a href="post_detail.php?id=<?php echo $post['post_id']; ?>" class="post-action-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span><?php echo $comment_count > 0 ? $comment_count : ''; ?></span>
              </a>
              
              <button class="post-action-btn" onclick="sharePost(<?php echo $post['post_id']; ?>)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="18" cy="5" r="3"></circle>
                  <circle cx="6" cy="12" r="3"></circle>
                  <circle cx="18" cy="19" r="3"></circle>
                  <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                  <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                </svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- 미디어 탭 -->
       <div id="media-tab" class="tab-content">
  <?php
  // 미디어가 있는 게시물만 가져오기
  $stmt = $pdo->prepare("
    SELECT DISTINCT p.* 
    FROM posts p
    INNER JOIN post_images pi ON p.post_id = pi.post_id
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC
  ");
  $stmt->execute([$user_id]);
  $media_posts = $stmt->fetchAll();
  ?>
  
  <?php if(empty($media_posts)): ?>
    <div class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
        <circle cx="8.5" cy="8.5" r="1.5"></circle>
        <polyline points="21 15 16 10 5 21"></polyline>
      </svg>
      <p>미디어가 없습니다</p>
    </div>
  <?php else: ?>
    <div class="media-grid">
      <?php foreach($media_posts as $media_post): ?>
        <?php
        // 첫 번째 미디어만 썸네일로 표시
        $stmt = $pdo->prepare("
          SELECT * FROM post_images 
          WHERE post_id = ? 
          ORDER BY image_order ASC 
          LIMIT 1
        ");
        $stmt->execute([$media_post['post_id']]);
        $thumbnail = $stmt->fetch();
        
        // 전체 미디어 개수
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM post_images WHERE post_id = ?");
        $stmt->execute([$media_post['post_id']]);
        $media_count = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
        $stmt->execute([$media_post['post_id']]);
        $media_likes = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
        $stmt->execute([$media_post['post_id']]);
        $media_comments = $stmt->fetch()['count'];
        ?>
        
        <a href="post_detail.php?id=<?php echo $media_post['post_id']; ?>" class="media-item">
          <?php if($thumbnail['media_type'] === 'video'): ?>
            <video src="<?php echo '../' . htmlspecialchars($thumbnail['image_path']); ?>" preload="metadata"></video>
            <div class="video-indicator">▶</div>
          <?php else: ?>
            <img src="<?php echo '../' . htmlspecialchars($thumbnail['image_path']); ?>" alt="media">
          <?php endif; ?>
          
          <?php if($media_count > 1): ?>
            <div class="media-count-badge"><?php echo $media_count; ?></div>
          <?php endif; ?>
          
          <div class="media-overlay">
            <div class="media-stats">
              <span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="2">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <?php echo $media_likes; ?>
              </span>
              <span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <?php echo $media_comments; ?>
              </span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

        <!-- 좋아요 탭 -->
        <!-- 좋아요 탭 -->
<div id="likes-tab" class="tab-content">
  <?php
  $stmt = $pdo->prepare("
    SELECT p.*, u.nickname, u.username, u.profile_img, u.user_id
    FROM posts p
    JOIN likes l ON p.post_id = l.post_id
    JOIN users u ON p.user_id = u.user_id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
  ");
  $stmt->execute([$user_id]);
  $liked_posts = $stmt->fetchAll();
  ?>
  
  <?php if(empty($liked_posts)): ?>
    <div class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
      </svg>
      <p>좋아요한 게시물이 없습니다</p>
    </div>
  <?php else: ?>
    <?php foreach($liked_posts as $liked_post): ?>
      <?php
      // 좋아요한 게시물의 미디어 가져오기
      $stmt = $pdo->prepare("
        SELECT * FROM post_images 
        WHERE post_id = ? 
        ORDER BY image_order ASC
      ");
      $stmt->execute([$liked_post['post_id']]);
      $liked_post_media = $stmt->fetchAll();
      
      $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM likes WHERE post_id = ?");
      $stmt->execute([$liked_post['post_id']]);
      $like_count = $stmt->fetch()['count'];
      
      $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
      $stmt->execute([$liked_post['post_id']]);
      $comment_count = $stmt->fetch()['count'];
      
      $liked_user_liked = false;
      if (isset($_SESSION['user'])) {
          $stmt = $pdo->prepare("SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?");
          $stmt->execute([$_SESSION['user']['user_id'], $liked_post['post_id']]);
          $liked_user_liked = $stmt->fetch() ? true : false;
      }
      ?>
      <div class="post-card mb-4" onclick="goToPostDetail(<?php echo $liked_post['post_id']; ?>, event)" style="cursor: pointer;">
        <div class="post-header">
          <div class="d-flex align-items-center gap-3">
            <a href="user_profile.php?id=<?php echo $liked_post['user_id']; ?>">
              <img src="<?php echo $liked_post['profile_img'] ? '../'.htmlspecialchars($liked_post['profile_img']) : '../assets/images/sample.png'; ?>" 
                   class="profile-img-sm" alt="profile">
            </a>
            <div class="flex-grow-1">
              <a href="user_profile.php?id=<?php echo $liked_post['user_id']; ?>" class="text-decoration-none">
                <strong class="post-author"><?php echo htmlspecialchars($liked_post['nickname']); ?></strong>
              </a>
              <div class="post-time"><?php echo htmlspecialchars($liked_post['created_at']); ?></div>
            </div>
          </div>
        </div>

        <div class="post-content">
          <?php if($liked_post['content']): ?>
            <p><?php echo nl2br(htmlspecialchars($liked_post['content'])); ?></p>
          <?php endif; ?>
          
          <?php if($liked_post_media): ?>
            <div class="post-media-grid" data-count="<?php echo count($liked_post_media); ?>">
              <?php foreach($liked_post_media as $media): ?>
                <div class="post-media-item" data-media-type="<?php echo $media['media_type']; ?>">
                  <?php if($media['media_type'] === 'video'): ?>
                    <video 
                      src="<?php echo '../' . htmlspecialchars($media['image_path']); ?>" 
                      controls
                      preload="metadata"
                      onclick="event.stopPropagation()">
                    </video>
                  <?php else: ?>
                    <img 
                      src="<?php echo '../' . htmlspecialchars($media['image_path']); ?>" 
                      alt="post media"
                      onclick="event.stopPropagation()">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="post-footer">
          <button class="post-action-btn <?php echo $liked_user_liked ? 'liked' : ''; ?>" 
                  onclick="toggleLike(<?php echo $liked_post['post_id']; ?>, this)"
                  data-post-id="<?php echo $liked_post['post_id']; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $liked_user_liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            <span class="like-count"><?php echo $like_count > 0 ? $like_count : ''; ?></span>
          </button>
          
          <a href="post_detail.php?id=<?php echo $liked_post['post_id']; ?>" class="post-action-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span><?php echo $comment_count > 0 ? $comment_count : ''; ?></span>
          </a>
          
          <button class="post-action-btn" onclick="sharePost(<?php echo $liked_post['post_id']; ?>)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="18" cy="5" r="3"></circle>
              <circle cx="6" cy="12" r="3"></circle>
              <circle cx="18" cy="19" r="3"></circle>
              <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
              <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
            </svg>
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
      <?php endif; ?>

    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css">

<style>
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
.post-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.post-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
.video-indicator {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 50px;
  height: 50px;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  pointer-events: none;
}

.media-count-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.media-item video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.media-item:hover video {
  transform: scale(1.05);
}
  .profile-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-profile-menu {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-profile-menu:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.btn-blocked {
  padding: 8px 24px;
  border-radius: 8px;
  border: 1px solid #dc3545;
  background: transparent;
  color: #dc3545;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-blocked:hover {
  background: #dc3545;
  color: white;
}

.blocked-content-notice {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.blocked-content-notice svg {
  margin-bottom: 16px;
  opacity: 0.5;
  color: var(--text-secondary);
}

.blocked-content-notice p {
  margin-bottom: 16px;
  color: var(--text-secondary);
}

/* 비공개 계정 알림 스타일 */
.private-account-notice {
  text-align: center;
  padding: 80px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.private-account-notice svg {
  margin-bottom: 24px;
  opacity: 0.5;
  color: var(--text-secondary);
}

.private-account-notice h4 {
  margin-bottom: 12px;
  color: var(--text-primary);
}

.private-account-notice p {
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.btn-pending {
  padding: 8px 24px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-secondary);
  font-weight: 600;
  cursor: default;
  transition: all 0.2s;
}

/* 기존 스타일 유지 */
.stat-link {
  text-decoration: none;
  color: var(--text-primary);
  transition: all 0.2s;
}

.stat-link:hover {
  color: var(--primary-color);
}

.profile-info {
  position: relative;
  z-index: 10;
}

.profile-avatar {
  position: relative;
  z-index: 20;
}

.profile-cover-swiper {
  position: relative;
  height: 250px;
  overflow: hidden;
  z-index: 1;
}

.profile-cover-swiper .swiper-container {
  width: 100%;
  height: 100%;
}

.profile-cover-slide {
  width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
}

.profile-cover-swiper .swiper-button-prev,
.profile-cover-swiper .swiper-button-next {
  color: white;
  background: rgba(0, 0, 0, 0.5);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  transition: all 0.2s;
}

.profile-cover-swiper .swiper-button-prev::after,
.profile-cover-swiper .swiper-button-next::after {
  font-size: 18px;
}

.profile-cover-swiper .swiper-button-prev:hover,
.profile-cover-swiper .swiper-button-next:hover {
  background: rgba(0, 0, 0, 0.8);
}

.profile-cover-swiper .swiper-pagination {
  bottom: 10px;
}

.profile-cover-swiper .swiper-pagination-bullet {
  background: rgba(255, 255, 255, 0.6);
  width: 8px;
  height: 8px;
}

.profile-cover-swiper .swiper-pagination-bullet-active {
  background: white;
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

.media-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
}

@media (max-width: 768px) {
  .media-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.media-item {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  cursor: pointer;
  display: block;
}

.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}

.media-item:hover img {
  transform: scale(1.05);
}

.media-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s;
}

.media-item:hover .media-overlay {
  opacity: 1;
}

.media-stats {
  display: flex;
  gap: 20px;
  color: white;
  font-weight: 600;
}

.media-stats span {
  display: flex;
  align-items: center;
  gap: 6px;
}

.post-action-btn.liked {
  color: #e0245e;
}

.post-action-btn.liked svg {
  fill: #e0245e;
}

.post-action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s;
  text-decoration: none;
  color: var(--text-secondary);
}

.post-action-btn:hover {
  transform: scale(1.05);
  color: var(--primary-color);
}

.like-count {
  font-size: 14px;
  font-weight: 500;
}

.auto-dismiss {
  animation: fadeOut 0.5s ease-in-out 3s forwards;
}

@keyframes fadeOut {
  0% {
    opacity: 1;
  }
  100% {
    opacity: 0;
    display: none;
  }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js"></script>
<script>
  function blockUser(userId, nickname) {
  if (!confirm(`${nickname}님을 차단하시겠습니까?\n\n차단하면:\n• 서로의 게시물을 볼 수 없습니다\n• 검색에서 찾을 수 없습니다\n• 팔로우 관계가 해제됩니다`)) return;
  
  fetch('<?php echo BASE_URL; ?>/api/block_toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `user_id=${userId}&action=block`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showNotification(data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showNotification(data.message, 'error');
    }
  })
  .catch(err => {
    console.error('Error:', err);
    showNotification('차단에 실패했습니다.', 'error');
  });
}

function unblockUser(userId, nickname) {
  if (!confirm(`${nickname}님의 차단을 해제하시겠습니까?`)) return;
  
  fetch('<?php echo BASE_URL; ?>/api/block_toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `user_id=${userId}&action=unblock`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showNotification(data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      showNotification(data.message, 'error');
    }
  })
  .catch(err => {
    console.error('Error:', err);
    showNotification('차단 해제에 실패했습니다.', 'error');
  });
}

function showNotification(message, type = 'success') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
  alertDiv.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  document.body.appendChild(alertDiv);
  setTimeout(() => alertDiv.remove(), 3500);
}
document.addEventListener('DOMContentLoaded', function() {
  // 탭 전환
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const tabName = this.dataset.tab;
      
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
      });
      
      const targetTab = document.getElementById(tabName + '-tab');
      if (targetTab) {
        targetTab.classList.add('active');
      }
    });
  });
  
  // 스와이퍼 초기화
  const profileSwiper = document.getElementById('profileHeaderSwiper');
  if (profileSwiper) {
    new Swiper('#profileHeaderSwiper', {
      loop: true,
      autoplay: {
        delay: 5000,
        disableOnInteraction: false,
      },
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      grabCursor: true,
      effect: 'fade',
      fadeEffect: {
        crossFade: true
      }
    });
  }
  
  // 자동 사라지는 알림
  const alerts = document.querySelectorAll('.auto-dismiss');
  alerts.forEach(alert => {
    setTimeout(() => alert.remove(), 3500);
  });
});

function toggleLike(postId, button) {
  <?php if(!isset($_SESSION['user'])): ?>
    window.location.href = '<?php echo BASE_URL; ?>/pages/login.php';
    return;
  <?php endif; ?>
  
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
      const svg = button.querySelector('svg');
      const countSpan = button.querySelector('.like-count');
      
      if (data.liked) {
        button.classList.add('liked');
        svg.setAttribute('fill', 'currentColor');
      } else {
        button.classList.remove('liked');
        svg.setAttribute('fill', 'none');
      }
      
      countSpan.textContent = data.like_count > 0 ? data.like_count : '';
    } else {
      showNotification(data.message || '좋아요 처리에 실패했습니다.', 'error');
    }
  })
  .catch(err => {
    console.error('Like error:', err);
    showNotification('좋아요 처리에 실패했습니다.', 'error');
  });
}

function sharePost(postId) {
  const url = window.location.origin + '/pages/post_detail.php?id=' + postId;
  
  if (navigator.share) {
    navigator.share({
      title: '게시물 공유',
      url: url
    }).catch(err => console.log('Share cancelled'));
  } else {
    navigator.clipboard.writeText(url).then(() => {
      showNotification('링크가 복사되었습니다!', 'success');
    }).catch(() => {
      alert('링크 복사에 실패했습니다.');
    });
  }
}

function deletePost(postId) {
  if (!confirm('정말 삭제하시겠습니까?')) return;
  
  const formData = new FormData();
  formData.append('id', postId);
  
  fetch('post_delete.php', {
    method: 'POST',
    body: formData
  })
  .then(() => {
    showNotification('게시물이 삭제되었습니다.', 'success');
    setTimeout(() => location.reload(), 1000);
  })
  .catch(err => {
    console.error('Delete error:', err);
    showNotification('삭제에 실패했습니다.', 'error');
  });
}
function goToPostDetail(postId, event) {
  // 드롭다운 메뉴나 버튼 클릭 시 이동 방지
  if (event.target.closest('.dropdown') || 
      event.target.closest('.post-action-btn') || 
      event.target.closest('video') ||
      event.target.closest('img')) {
    return;
  }
  window.location.href = 'post_detail.php?id=' + postId;
}
function showNotification(message, type = 'success') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show auto-dismiss`;
  alertDiv.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
  alertDiv.innerHTML = `
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  document.body.appendChild(alertDiv);
  
  setTimeout(() => alertDiv.remove(), 3500);
}
function startDirectMessage(userId) {
  // 세션 확인
  <?php if(!isset($_SESSION['user'])): ?>
    window.location.href = '<?php echo BASE_URL; ?>/pages/login.php';
    return;
  <?php endif; ?>
  
  // 1:1 대화방이 이미 있는지 확인하고 없으면 생성
  const formData = new FormData();
  formData.append('is_group', '0');
  formData.append('user_ids', JSON.stringify([userId]));
  
  fetch('<?php echo BASE_URL; ?>/api/dm_create_conversation.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // 대화방 페이지로 이동 (id 파라미터 사용)
      window.location.href = '<?php echo BASE_URL; ?>/pages/dm_conversation.php?id=' + data.conversation_id;
    } else {
      showNotification(data.message || 'DM을 시작할 수 없습니다.', 'error');
    }
  })
  .catch(err => {
    console.error('DM start error:', err);
    showNotification('오류가 발생했습니다.', 'error');
  });
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>