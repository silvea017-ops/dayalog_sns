<?php
// public/pages/blocked_users.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/block.php';
require_once INCLUDES_PATH . '/header.php';

$current_user_id = $_SESSION['user']['user_id'];
$blocked_users = getBlockedUsers($pdo, $current_user_id);
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
        <h4>차단된 사용자</h4>
        <p class="text-muted small mb-0">차단한 사용자는 회원님의 게시물을 볼 수 없으며, 검색에서도 찾을 수 없습니다.</p>
      </div>

      <?php if(empty($blocked_users)): ?>
        <div class="empty-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
          </svg>
          <p>차단한 사용자가 없습니다</p>
        </div>
      <?php else: ?>
        <div class="blocked-users-list">
          <?php foreach($blocked_users as $blocked): ?>
            <div class="user-item" id="blocked-user-<?php echo $blocked['user_id']; ?>">
              <div class="user-info-link">
                <img src="<?php echo getProfileImageUrl($blocked['profile_img']); ?>" 
                     class="user-avatar" alt="profile">
                <div class="user-info">
                  <strong><?php echo htmlspecialchars($blocked['nickname']); ?></strong>
                  <div class="text-muted">@<?php echo htmlspecialchars($blocked['username']); ?></div>
                  <div class="text-muted small">차단일: <?php echo date('Y-m-d', strtotime($blocked['blocked_at'])); ?></div>
                </div>
              </div>
              <button class="btn btn-sm btn-outline-secondary" 
                      onclick="unblockUser(<?php echo $blocked['user_id']; ?>, '<?php echo htmlspecialchars($blocked['nickname']); ?>')">
                차단 해제
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
.blocked-users-list {
  background: var(--bg-primary);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.user-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
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
  color: var(--text-primary);
}

.user-info .text-muted {
  font-size: 14px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.empty-state svg {
  margin-bottom: 20px;
  opacity: 0.5;
}
</style>

<script>
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
      document.getElementById(`blocked-user-${userId}`).remove();
      
      // 모든 항목이 제거되면 빈 상태 표시
      if (document.querySelectorAll('.user-item').length === 0) {
        location.reload();
      }
      
      showNotification(data.message, 'success');
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
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>