<?php
// public/pages/search.php (사용자/게시글 탭 추가)
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/block.php';
require_once INCLUDES_PATH . '/header.php';

$query = $_GET['q'] ?? '';
$tab = $_GET['tab'] ?? 'users'; // 기본값: users
$user_results = [];
$post_results = [];

if ($query) {
    $search_term = '%' . $query . '%';
    $current_user_id = $_SESSION['user']['user_id'] ?? null;
    
    // 사용자 검색
    if ($current_user_id) {
        $stmt = $pdo->prepare("
            SELECT user_id, username, nickname, profile_img, bio 
            FROM users 
            WHERE (username LIKE ? OR nickname LIKE ?)
            AND user_id NOT IN (
                SELECT blocked_id FROM blocks WHERE blocker_id = ?
                UNION
                SELECT blocker_id FROM blocks WHERE blocked_id = ?
            )
            AND user_id != ?
            ORDER BY 
                CASE WHEN username LIKE ? THEN 1 ELSE 2 END,
                username ASC
            LIMIT 20
        ");
        $stmt->execute([
            $search_term, 
            $search_term, 
            $current_user_id, 
            $current_user_id,
            $current_user_id,
            $query . '%'
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, username, nickname, profile_img, bio 
            FROM users 
            WHERE (username LIKE ? OR nickname LIKE ?)
            ORDER BY 
                CASE WHEN username LIKE ? THEN 1 ELSE 2 END,
                username ASC
            LIMIT 20
        ");
        $stmt->execute([$search_term, $search_term, $query . '%']);
    }
    $user_results = $stmt->fetchAll();
    
    // 게시글 검색 (비공개 계정 필터링 추가)
    if ($current_user_id) {
        // 차단한 사용자와 나를 차단한 사용자의 게시글 제외
        // 비공개 계정은 본인이거나 팔로우 중인 경우만 표시
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.nickname, u.profile_img,
                   (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
                   (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.content LIKE ?
            AND p.user_id NOT IN (
                SELECT blocked_id FROM blocks WHERE blocker_id = ?
                UNION
                SELECT blocker_id FROM blocks WHERE blocked_id = ?
            )
            AND (
                u.is_private = 0
                OR p.user_id = ?
                OR EXISTS (
                    SELECT 1 FROM follows f 
                    WHERE f.follower_id = ? 
                    AND f.following_id = p.user_id 
                    AND f.status = 'accepted'
                )
            )
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$search_term, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    } else {
        // 비로그인 사용자는 공개 계정의 게시글만 표시
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.nickname, u.profile_img,
                   (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) as like_count,
                   (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
            FROM posts p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.content LIKE ?
            AND u.is_private = 0
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$search_term]);
    }
    $post_results = $stmt->fetchAll();
}

$user_count = count($user_results);
$post_count = count($post_results);
?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      
      <div class="search-header mb-4">
        <form method="get" action="search.php" class="search-form">
          <div class="input-group">
            <span class="input-group-text">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
              </svg>
            </span>
            <input type="text" name="q" class="form-control" placeholder="사용자 또는 게시글 검색..." 
                   value="<?php echo htmlspecialchars($query); ?>" autofocus>
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
          </div>
        </form>
      </div>

      <?php if($query): ?>
        <!-- 탭 네비게이션 -->
        <div class="search-tabs mb-3">
          <a href="?q=<?php echo urlencode($query); ?>&tab=users" 
             class="tab-item <?php echo $tab === 'users' ? 'active' : ''; ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            사용자 <span class="count">(<?php echo $user_count; ?>)</span>
          </a>
          <a href="?q=<?php echo urlencode($query); ?>&tab=posts" 
             class="tab-item <?php echo $tab === 'posts' ? 'active' : ''; ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            게시글 <span class="count">(<?php echo $post_count; ?>)</span>
          </a>
        </div>

        <!-- 사용자 탭 -->
        <?php if($tab === 'users'): ?>
          <?php if(empty($user_results)): ?>
            <div class="empty-state">
              <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <p>검색 결과가 없습니다</p>
              <small class="text-muted">"<?php echo htmlspecialchars($query); ?>"에 대한 사용자를 찾을 수 없습니다</small>
            </div>
          <?php else: ?>
            <div class="search-results">
              <?php foreach($user_results as $user): ?>
                <?php
                $is_following = false;
                $follow_status = null;
                
                if (isset($_SESSION['user']) && $_SESSION['user']['user_id'] != $user['user_id']) {
                    $stmt = $pdo->prepare("SELECT status FROM follows WHERE follower_id = ? AND following_id = ?");
                    $stmt->execute([$_SESSION['user']['user_id'], $user['user_id']]);
                    $follow_record = $stmt->fetch();
                    if ($follow_record) {
                        $follow_status = $follow_record['status'];
                        $is_following = ($follow_status === 'accepted');
                    }
                }
                
                $is_own = isset($_SESSION['user']) && $_SESSION['user']['user_id'] == $user['user_id'];
                ?>
                
                <div class="user-item">
                  <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $user['user_id']; ?>" class="user-info-link">
                    <img src="<?php echo getProfileImageUrl($user['profile_img']); ?>" 
                         class="user-avatar" alt="profile">
                    <div class="user-info">
                      <strong><?php echo htmlspecialchars($user['nickname']); ?></strong>
                      <div class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></div>
                      <?php if($user['bio']): ?>
                        <div class="user-bio"><?php echo htmlspecialchars($user['bio']); ?></div>
                      <?php endif; ?>
                    </div>
                  </a>
                  
                  <?php if(!$is_own && isset($_SESSION['user'])): ?>
                    <form method="post" action="<?php echo BASE_URL; ?>/api/follow_toggle.php" class="follow-form">
                      <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                      <input type="hidden" name="redirect" value="<?php echo BASE_URL; ?>/pages/search.php?q=<?php echo urlencode($query); ?>&tab=users">
                      <?php if($follow_status === 'pending'): ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">요청됨</button>
                      <?php else: ?>
                        <button type="submit" class="btn btn-sm <?php echo $is_following ? 'btn-outline-secondary' : 'btn-primary'; ?>">
                          <?php echo $is_following ? '팔로잉' : '팔로우'; ?>
                        </button>
                      <?php endif; ?>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <!-- 게시글 탭 -->
        <?php if($tab === 'posts'): ?>
          <?php if(empty($post_results)): ?>
            <div class="empty-state">
              <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
              <p>검색 결과가 없습니다</p>
              <small class="text-muted">"<?php echo htmlspecialchars($query); ?>"에 대한 게시글을 찾을 수 없습니다</small>
            </div>
          <?php else: ?>
            <div class="search-results">
              <?php foreach($post_results as $post): ?>
                <div class="post-item">
                  <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $post['user_id']; ?>" class="post-author">
                    <img src="<?php echo getProfileImageUrl($post['profile_img']); ?>" 
                         class="post-avatar" alt="profile">
                    <div>
                      <strong><?php echo htmlspecialchars($post['nickname']); ?></strong>
                      <div class="text-muted small">@<?php echo htmlspecialchars($post['username']); ?></div>
                    </div>
                  </a>
                  
                  <a href="<?php echo BASE_URL; ?>/pages/post_detail.php?id=<?php echo $post['post_id']; ?>" class="post-content-link">
                    <div class="post-content">
                      <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>
                    
                    <?php if(!empty($post['image'])): ?>
                      <div class="post-image-container">
                        <img src="<?php echo BASE_URL . '/uploads/posts/' . $post['image']; ?>" 
                             class="post-image" alt="post image">
                      </div>
                    <?php endif; ?>
                    
                    <div class="post-meta">
                      <span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <?php echo $post['like_count']; ?>
                      </span>
                      <span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <?php echo $post['comment_count']; ?>
                      </span>
                      <span class="text-muted">
                        <?php echo date('Y.m.d', strtotime($post['created_at'])); ?>
                      </span>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      <?php else: ?>
        <div class="search-tips">
          <h5>검색하기</h5>
          <p class="text-muted">사용자 이름, 닉네임 또는 게시글 내용을 검색하세요</p>
          <ul class="tips-list">
            <li>정확한 사용자 이름을 입력하면 더 빠르게 찾을 수 있습니다</li>
            <li>닉네임이나 게시글의 일부만 입력해도 검색됩니다</li>
            <li>탭을 전환하여 사용자와 게시글을 따로 확인할 수 있습니다</li>
          </ul>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
.search-header {
  padding: 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.search-form .input-group-text {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-right: none;
  color: var(--text-secondary);
}

.search-form .form-control {
  border-left: none;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.search-form .form-control:focus {
  background: var(--bg-primary);
  color: var(--text-primary);
  box-shadow: none;
  border-color: var(--primary-color);
}

.search-tabs {
  display: flex;
  gap: 8px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  padding: 8px;
}

.tab-item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  text-decoration: none;
  color: var(--text-secondary);
  font-weight: 500;
  transition: all 0.2s;
}

.tab-item:hover {
  color: var(--text-primary);
  background: var(--bg-hover);
}

.tab-item.active {
  background: var(--primary-color);
  color: white;
}

.tab-item .count {
  font-size: 14px;
  opacity: 0.8;
}

.search-results {
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.user-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  transition: background 0.2s;
}

.user-item:last-child {
  border-bottom: none;
}

.user-item:hover {
  background: var(--bg-hover);
}

.user-info-link {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  text-decoration: none;
  color: var(--text-primary);
  min-width: 0;
}

.user-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-info strong {
  display: block;
  font-size: 15px;
}

.user-info .text-muted {
  font-size: 14px;
  margin-bottom: 4px;
}

.user-bio {
  font-size: 14px;
  color: var(--text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.follow-form {
  flex-shrink: 0;
}

.post-item {
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  transition: background 0.2s;
}

.post-item:last-child {
  border-bottom: none;
}

.post-item:hover {
  background: var(--bg-hover);
}

.post-author {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  text-decoration: none;
  color: var(--text-primary);
}

.post-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.post-content-link {
  text-decoration: none;
  color: var(--text-primary);
  display: block;
}

.post-content {
  font-size: 15px;
  line-height: 1.5;
  margin-bottom: 12px;
  word-break: break-word;
}

.post-image-container {
  margin-bottom: 12px;
  border-radius: 12px;
  overflow: hidden;
}

.post-image {
  width: 100%;
  max-height: 400px;
  object-fit: cover;
}

.post-meta {
  display: flex;
  gap: 16px;
  font-size: 14px;
  color: var(--text-secondary);
}

.post-meta span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.search-tips {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.search-tips h5 {
  margin-bottom: 12px;
  color: var(--text-primary);
}

.tips-list {
  text-align: left;
  max-width: 400px;
  margin: 24px auto 0;
  padding-left: 20px;
  color: var(--text-secondary);
}

.tips-list li {
  margin-bottom: 8px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.empty-state svg {
  margin-bottom: 20px;
  opacity: 0.5;
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>