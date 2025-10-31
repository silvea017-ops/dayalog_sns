<?php
// public/pages/follow_requests.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/notifications.php';

// 팔로우 요청 목록
$follow_requests = getFollowRequests($pdo, $_SESSION['user']['user_id']);

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      
      <div class="mb-4">
        <a href="javascript:history.back()" class="text-decoration-none text-muted d-flex align-items-center gap-2 mb-3">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          뒤로가기
        </a>
        <h4>팔로우 요청</h4>
      </div>

      <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
          <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']);
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if(empty($follow_requests)): ?>
        <div class="empty-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <p>팔로우 요청이 없습니다</p>
        </div>
      <?php else: ?>
        <div class="follow-requests-list">
          <?php foreach($follow_requests as $request): ?>
            <div class="follow-request-item">
              <a href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $request['user_id']; ?>" class="user-info-link">
                <img src="<?php echo getProfileImageUrl($request['profile_img']); ?>" 
                     class="user-avatar" alt="profile">
                <div class="user-info">
                  <strong><?php echo htmlspecialchars($request['nickname']); ?></strong>
                  <div class="text-muted">@<?php echo htmlspecialchars($request['username']); ?></div>
                  <?php if($request['bio']): ?>
                    <div class="user-bio"><?php echo htmlspecialchars($request['bio']); ?></div>
                  <?php endif; ?>
                </div>
              </a>
              <div class="request-actions">
                <form method="post" action="<?php echo BASE_URL; ?>/pages/handle_follow_request.php" class="d-inline">
                  <input type="hidden" name="follow_id" value="<?php echo $request['follow_id']; ?>">
                  <input type="hidden" name="action" value="accept">
                  <button type="submit" class="btn btn-sm btn-primary">수락</button>
                </form>
                <form method="post" action="<?php echo BASE_URL; ?>/pages/handle_follow_request.php" class="d-inline">
                  <input type="hidden" name="follow_id" value="<?php echo $request['follow_id']; ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">거절</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
.follow-requests-list {
  background: var(--bg-primary);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.follow-request-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
}

.follow-request-item:last-child {
  border-bottom: none;
}

.follow-request-item:hover {
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

.request-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
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
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.auto-dismiss');
  alerts.forEach(alert => {
    setTimeout(() => alert.remove(), 3500);
  });
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>