<?php
// public/pages/notifications.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/notifications.php';

$current_user_id = $_SESSION['user']['user_id'];

// 알림 목록 가져오기
$notifications = getNotifications($pdo, $current_user_id, 50, 0);

// 모든 알림을 읽음으로 표시
if (!empty($notifications)) {
    markAllNotificationsAsRead($pdo, $current_user_id);
}

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
        <h4>알림</h4>
      </div>

      <?php if(empty($notifications)): ?>
        <div class="empty-state">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <p>알림이 없습니다</p>
        </div>
      <?php else: ?>
        <div class="notifications-list">
          <?php foreach($notifications as $notification): ?>
            <?php
            $message = getNotificationMessage($notification);
            $link = getNotificationLink($notification, BASE_URL, $pdo);
            $is_unread = !$notification['is_read'];
            ?>
            <a href="<?php echo $link; ?>" class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
              <img src="<?php echo getProfileImageUrl($notification['from_profile_img'] ?? ''); ?>" 
                   class="notification-avatar" alt="profile">
              
              <div class="notification-content">
                <div class="notification-message">
                  <?php echo $message; ?>
                </div>
                <div class="notification-time">
  <?php echo formatPostDate($notification['created_at']); ?>
</div>
                
                <?php if($notification['type'] === 'like' || $notification['type'] === 'comment'): ?>
                  <?php if(!empty($notification['post_image'])): ?>
                    <div class="notification-preview">
                      <img src="<?php echo getUploadUrl($notification['post_image']); ?>" alt="post">
                    </div>
                  <?php elseif(!empty($notification['post_content'])): ?>
                    <div class="notification-preview-text">
                      <?php echo mb_substr(htmlspecialchars($notification['post_content']), 0, 50) . '...'; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
                
                <?php if($notification['type'] === 'comment' || $notification['type'] === 'reply'): ?>
                  <?php if(!empty($notification['comment_content'])): ?>
                    <div class="notification-preview-text">
                      <?php echo mb_substr(htmlspecialchars($notification['comment_content']), 0, 50) . '...'; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              
              <?php if($is_unread): ?>
                <div class="unread-indicator"></div>
              <?php endif; ?>
              
              <div class="notification-icon">
  <?php if($notification['type'] === 'follow_request' || $notification['type'] === 'follow_accept' || $notification['type'] === 'follow'): ?>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="8.5" cy="7" r="4"></circle>
      <line x1="20" y1="8" x2="20" y2="14"></line>
      <line x1="23" y1="11" x2="17" y2="11"></line>
    </svg>
  <?php elseif($notification['type'] === 'like'): ?>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
    </svg>
  <?php elseif($notification['type'] === 'comment' || $notification['type'] === 'reply'): ?>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </svg>
  <?php endif; ?>
</div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<style>
.notifications-list {
  background: var(--bg-primary);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  text-decoration: none;
  color: var(--text-primary);
  position: relative;
  transition: background 0.2s;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item:hover {
  background: var(--bg-hover);
}

.notification-item.unread {
  background: rgba(86, 105, 254, 0.05);
}

.notification-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-message {
  font-size: 15px;
  margin-bottom: 4px;
  word-break: break-word;
}

.notification-time {
  font-size: 13px;
  color: var(--text-secondary);
  margin-bottom: 8px;
}

.notification-preview {
  margin-top: 8px;
  border-radius: 8px;
  overflow: hidden;
  max-width: 200px;
}

.notification-preview img {
  width: 100%;
  height: auto;
  display: block;
}

.notification-preview-text {
  margin-top: 8px;
  padding: 8px 12px;
  background: var(--bg-secondary);
  border-radius: 8px;
  font-size: 14px;
  color: var(--text-secondary);
  line-height: 1.4;
}

.unread-indicator {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  width: 8px;
  height: 8px;
  background: var(--primary-color);
  border-radius: 50%;
}

.notification-icon {
  color: var(--text-secondary);
  flex-shrink: 0;
}

.notification-icon svg[fill="currentColor"] {
  color: #e0245e;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--text-secondary);
}

.empty-state svg {
  margin-bottom: 16px;
  opacity: 0.5;
}

.empty-state p {
  margin: 0;
  font-size: 16px;
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>