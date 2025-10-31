<?php
// follows.php - 비공개 계정의 팔로우 목록 접근 제한
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/header.php';

$user_id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'followers'; // followers or following

if (!$user_id) {
    header('Location: index.php');
    exit;
}

// 사용자 정보 조회
$stmt = $pdo->prepare("SELECT user_id, nickname, username, is_private FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user']['user_id'] ?? null;
$is_own_profile = $current_user_id && $current_user_id == $user_id;

// 비공개 계정 접근 권한 체크
$can_view_follows = true;
if ($user['is_private'] && !$is_own_profile) {
    // 팔로우 관계 확인
    if ($current_user_id) {
        $stmt = $pdo->prepare("SELECT status FROM follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$current_user_id, $user_id]);
        $follow = $stmt->fetch();
        
        if (!$follow || $follow['status'] !== 'accepted') {
            $can_view_follows = false;
        }
    } else {
        $can_view_follows = false;
    }
}

// 접근 권한이 없는 경우
if (!$can_view_follows) {
    ?>
    <div class="container mt-4">
      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="private-follows-notice">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <h4>비공개 계정입니다</h4>
            <p><?php echo htmlspecialchars($user['nickname']); ?>님의 <?php echo $type === 'followers' ? '팔로워' : '팔로잉'; ?> 목록은 비공개입니다.</p>
            <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $user_id; ?>" class="btn btn-primary mt-3">프로필로 돌아가기</a>
          </div>
        </div>
      </div>
    </div>
    
    <style>
    .private-follows-notice {
      text-align: center;
      padding: 80px 20px;
      background: var(--bg-primary);
      border-radius: 12px;
      border: 1px solid var(--border-color);
    }
    .private-follows-notice svg {
      margin-bottom: 24px;
      opacity: 0.5;
      color: var(--text-secondary);
    }
    .private-follows-notice h4 {
      margin-bottom: 12px;
      color: var(--text-primary);
    }
    .private-follows-notice p {
      color: var(--text-secondary);
      margin-bottom: 8px;
    }
    </style>
    <?php
    require_once INCLUDES_PATH . '/footer.php';
    exit;
}

// 팔로우 목록 조회 (접근 가능한 경우)
if ($type === 'followers') {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.nickname, u.username, u.profile_img, u.bio, u.is_private,
               CASE WHEN f2.follower_id IS NOT NULL THEN 1 ELSE 0 END as is_following
        FROM follows f
        JOIN users u ON f.follower_id = u.user_id
        LEFT JOIN follows f2 ON f2.follower_id = ? AND f2.following_id = u.user_id AND f2.status = 'accepted'
        WHERE f.following_id = ? AND f.status = 'accepted'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$current_user_id ?? 0, $user_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.nickname, u.username, u.profile_img, u.bio, u.is_private,
               CASE WHEN f2.follower_id IS NOT NULL THEN 1 ELSE 0 END as is_following
        FROM follows f
        JOIN users u ON f.following_id = u.user_id
        LEFT JOIN follows f2 ON f2.follower_id = ? AND f2.following_id = u.user_id AND f2.status = 'accepted'
        WHERE f.follower_id = ? AND f.status = 'accepted'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$current_user_id ?? 0, $user_id]);
}

$follows = $stmt->fetchAll();
?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="follows-header mb-4">
        <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $user_id; ?>" class="back-btn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"></polyline>
          </svg>
        </a>
        <div class="flex-grow-1">
          <h4 class="mb-0">
            <?php echo htmlspecialchars($user['nickname']); ?>
            <?php if($user['is_private']): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-1" style="vertical-align: middle;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            <?php endif; ?>
          </h4>
          <p class="text-muted small mb-0">@<?php echo htmlspecialchars($user['username']); ?></p>
        </div>
      </div>

      <div class="follows-tabs mb-3">
        <a href="?id=<?php echo $user_id; ?>&type=followers" 
           class="tab-link <?php echo $type === 'followers' ? 'active' : ''; ?>">
          팔로워
        </a>
        <a href="?id=<?php echo $user_id; ?>&type=following" 
           class="tab-link <?php echo $type === 'following' ? 'active' : ''; ?>">
          팔로잉
        </a>
      </div>

      <?php if (empty($follows)): ?>
        <div class="empty-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <p><?php echo $type === 'followers' ? '팔로워가 없습니다' : '팔로잉이 없습니다'; ?></p>
        </div>
      <?php else: ?>
        <?php foreach ($follows as $follow): ?>
          <div class="user-card">
            <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $follow['user_id']; ?>" class="user-info">
              <img src="<?php echo getProfileImageUrl($follow['profile_img']); ?>" class="user-avatar" alt="profile">
              <div class="user-details">
                <div class="user-name">
                  <?php echo htmlspecialchars($follow['nickname']); ?>
                  <?php if($follow['is_private']): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ms-1" style="vertical-align: middle;">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                      <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                  <?php endif; ?>
                </div>
                <div class="user-username">@<?php echo htmlspecialchars($follow['username']); ?></div>
                <?php if ($follow['bio']): ?>
                  <div class="user-bio"><?php echo htmlspecialchars($follow['bio']); ?></div>
                <?php endif; ?>
              </div>
            </a>
            
            <?php if ($current_user_id && $current_user_id != $follow['user_id']): ?>
              <form method="post" action="<?php echo BASE_URL; ?>/api/follow_toggle.php" class="follow-form">
                <input type="hidden" name="user_id" value="<?php echo $follow['user_id']; ?>">
                <input type="hidden" name="redirect" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                <button type="submit" class="btn <?php echo $follow['is_following'] ? 'btn-following' : 'btn-follow'; ?>">
                  <?php echo $follow['is_following'] ? '팔로잉' : '팔로우'; ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.follows-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.back-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--bg-secondary);
  color: var(--text-primary);
  text-decoration: none;
  transition: all 0.2s;
}

.back-btn:hover {
  background: var(--bg-hover);
  transform: scale(1.05);
}

.follows-tabs {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-primary);
  border-radius: 12px 12px 0 0;
  overflow: hidden;
}

.tab-link {
  flex: 1;
  padding: 16px;
  text-align: center;
  text-decoration: none;
  color: var(--text-secondary);
  font-weight: 600;
  transition: all 0.2s;
  position: relative;
}

.tab-link:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.tab-link.active {
  color: var(--primary-color);
}

.tab-link.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--primary-color);
}

.user-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  margin-bottom: 12px;
  transition: all 0.2s;
}

.user-card:hover {
  border-color: var(--primary-color);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.user-info {
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

.user-details {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 600;
  font-size: 15px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.user-username {
  color: var(--text-secondary);
  font-size: 14px;
  margin-top: 2px;
}

.user-bio {
  color: var(--text-secondary);
  font-size: 14px;
  margin-top: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.follow-form {
  flex-shrink: 0;
}

.btn-follow,
.btn-following {
  padding: 8px 20px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 14px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-follow {
  background: var(--primary-color);
  color: white;
}

.btn-follow:hover {
  background: var(--primary-hover);
  transform: scale(1.05);
}

.btn-following {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-following:hover {
  background: #fee;
  border-color: #fcc;
  color: #c00;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.empty-state svg {
  margin-bottom: 16px;
  opacity: 0.5;
  color: var(--text-secondary);
}

.empty-state p {
  color: var(--text-secondary);
  margin: 0;
}

.private-follows-notice {
  text-align: center;
  padding: 80px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.private-follows-notice svg {
  margin-bottom: 24px;
  opacity: 0.5;
  color: var(--text-secondary);
}

.private-follows-notice h4 {
  margin-bottom: 12px;
  color: var(--text-primary);
}

.private-follows-notice p {
  color: var(--text-secondary);
  margin-bottom: 8px;
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>border-radius: 20px;
  font-weight: 600;
  font-size: 14px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-follow {
  background: var(--primary-color);
  color: white;
}

.btn-follow:hover {
  background: var(--primary-hover);
  transform: scale(1.05);
}

.btn-following {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.btn-following:hover {
  background: #fee;
  border-color: #fcc;
  color: #c00;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.empty-state svg {
  margin-bottom: 16px;
  opacity: 0.5;
  color: var(--text-secondary);
}

.empty-state p {
  color: var(--text-secondary);
  margin: 0;
}

.private-follows-notice {
  text-align: center;
  padding: 80px 20px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
}

.private-follows-notice svg {
  margin-bottom: 24px;
  opacity: 0.5;
  color: var(--text-secondary);
}

.private-follows-notice h4 {
  margin-bottom: 12px;
}

.private-follows-notice p {
  color: var(--text-secondary);
  margin-bottom: 8px;
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>