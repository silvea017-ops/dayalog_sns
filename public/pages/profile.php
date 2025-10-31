<?php
// public/pages/profile.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';
$user = $_SESSION['user'];
$errors = [];

$forbidden_usernames = ['admin', 'administrator', 'dayalog', 'root', 'moderator', 'mod', 'support', 'help', 'system'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $dm_permission = $_POST['dm_permission'] ?? 'everyone';
    
    if (!$nickname) $errors[] = '닉네임은 필수입니다.';
    
    if ($new_username && $new_username !== $user['username']) {
        if (in_array(strtolower($new_username), $forbidden_usernames)) {
            $errors[] = '사용할 수 없는 사용자명입니다.';
        } else {
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("SELECT username_changes, last_username_change FROM users WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $change_info = $stmt->fetch();
            
            $changes_today = 0;
            if ($change_info['last_username_change'] === $today) {
                $changes_today = $change_info['username_changes'];
            }
            
            if ($changes_today >= 3) {
                $errors[] = '사용자명은 하루에 3회까지만 변경할 수 있습니다.';
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                $stmt->execute([$new_username, $user['user_id']]);
                if ($stmt->fetch()) {
                    $errors[] = '이미 사용 중인 사용자명입니다.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO username_history (user_id, old_username, new_username) VALUES (?, ?, ?)");
                    $stmt->execute([$user['user_id'], $user['username'], $new_username]);
                    
                    if ($change_info['last_username_change'] === $today) {
                        $new_changes = $changes_today + 1;
                    } else {
                        $new_changes = 1;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, username_changes = ?, last_username_change = ? WHERE user_id = ?");
                    $stmt->execute([$new_username, $new_changes, $today, $user['user_id']]);
                    
                    $user['username'] = $new_username;
                }
            }
        }
    }
    
    if (!empty($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $uploaddir = __DIR__ . '/../uploads/';
        $ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $_SESSION['user']['user_id'] . '_' . time() . '.' . $ext;
        $dest = $uploaddir . $filename;
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $dest)) {
            $profile_img = 'uploads/' . $filename;
            
            if ($user['profile_img'] && 
                $user['profile_img'] !== 'assets/images/sample.png' &&
                file_exists(__DIR__ . '/../' . $user['profile_img'])) {
                unlink(__DIR__ . '/../' . $user['profile_img']);
            }
        } else {
            $errors[] = '프로필 이미지 업로드 실패';
        }
    } else {
        $profile_img = $user['profile_img'];
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET nickname = ?, bio = ?, profile_img = ?, is_private = ?, dm_permission = ? WHERE user_id = ?");
        $stmt->execute([$nickname, $bio, $profile_img, $is_private, $dm_permission, $_SESSION['user']['user_id']]);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['user_id']]);
        $_SESSION['user'] = $stmt->fetch();
        $_SESSION['success_message'] = '프로필이 업데이트되었습니다.';
        header('Location: profile.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM cover_images WHERE user_id = ? ORDER BY display_order ASC, created_at DESC");
$stmt->execute([$user['user_id']]);
$cover_images = $stmt->fetchAll();

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT username_changes, last_username_change FROM users WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$change_info = $stmt->fetch();
$remaining_changes = 3;
if ($change_info['last_username_change'] === $today) {
    $remaining_changes = 3 - $change_info['username_changes'];
}

require_once INCLUDES_PATH . '/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="profile-edit-card">
      <h3 class="mb-4">프로필 편집</h3>
      
      <?php if($errors) foreach($errors as $e): ?>
        <div class="alert alert-danger auto-dismiss"><?php echo htmlspecialchars($e); ?></div>
      <?php endforeach; ?>
      
      <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
          <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']);
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      
      <!-- 헤더 이미지 관리 섹션 -->
      <div class="mb-5">
        <h5 class="mb-3">헤더 이미지</h5>
        
        <?php if(!empty($cover_images)): ?>
          <div class="header-preview-swiper mb-4">
            <div class="swiper-container" id="headerSwiper">
              <div class="swiper-wrapper">
                <?php foreach($cover_images as $cover): ?>
                <div class="swiper-slide">
                  <img src="<?php echo '../'.htmlspecialchars($cover['image_path']); ?>" alt="header">
                </div>
                <?php endforeach; ?>
              </div>
              <div class="swiper-button-prev"></div>
              <div class="swiper-button-next"></div>
              <div class="swiper-pagination"></div>
            </div>
            <div class="header-controls">
              <label style="display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px; cursor: pointer;">
                <input type="checkbox" id="autoplayToggle" class="toggle-checkbox" checked>
                <span>자동 슬라이드</span>
              </label>
              <span id="autoplayStatus">켜짐</span>
            </div>
          </div>
        <?php endif; ?>
        
        <div class="cover-images-grid" id="coverImagesContainer">
          <?php if(empty($cover_images)): ?>
            <div class="empty-covers">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
              </svg>
              <p>등록된 헤더 이미지가 없습니다</p>
            </div>
          <?php else: ?>
            <?php foreach($cover_images as $cover): ?>
            <div class="cover-item <?php echo $cover['is_active'] ? 'active' : ''; ?>" data-cover-id="<?php echo $cover['cover_id']; ?>" draggable="true">
              <input type="checkbox" class="image-checkbox" data-cover-id="<?php echo $cover['cover_id']; ?>">
              <img src="<?php echo '../'.htmlspecialchars($cover['image_path']); ?>" alt="cover">
              <div class="cover-actions">
                <button class="btn-cover-action" onclick="setActiveCover(<?php echo $cover['cover_id']; ?>)" title="활성화">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $cover['is_active'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </button>
                <button class="btn-cover-action btn-delete" onclick="deleteCover(<?php echo $cover['cover_id']; ?>)" title="삭제">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                  </svg>
                </button>
              </div>
              <div class="drag-handle">⋮⋮</div>
              <?php if($cover['is_active']): ?>
                <div class="active-badge">활성</div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        
        <form method="post" action="add_cover.php" enctype="multipart/form-data" id="addCoverForm">
          <div class="d-flex gap-2">
            <label class="btn btn-outline-primary flex-grow-1">
              <input type="file" name="cover_img" accept="image/*" hidden onchange="document.getElementById('addCoverForm').submit();">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              헤더 이미지 추가
            </label>
            <button type="button" class="btn btn-danger" id="deleteBatchBtn" style="display: none;">선택된 이미지 삭제</button>
            <button type="button" class="btn btn-secondary" id="deselectBtn" style="display: none;">선택 해제</button>
          </div>
        </form>
      </div>
      
      <hr class="my-4">
      
      <form method="post" enctype="multipart/form-data">
        <div class="mb-4 text-center">
          <div class="profile-img-preview-container">
            <img id="profilePreview" 
                 src="<?php echo $user['profile_img'] ? '../'.htmlspecialchars($user['profile_img']) : '../assets/images/sample.png'; ?>" 
                 class="profile-img-preview" 
                 alt="profile">
          </div>
          <div class="mt-3">
            <label class="custom-file-upload">
              <input type="file" name="profile_img" accept="image/*" id="profileImageInput">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              프로필 사진 변경
            </label>
          </div>
        </div>
        
        <div class="mb-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_private" id="isPrivateSwitch" 
                   <?php echo ($user['is_private'] ?? 0) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="isPrivateSwitch">
              <strong>비공개 계정</strong>
              <div class="text-muted small mt-1">
                비공개 계정으로 설정하면 팔로워만 회원님의 게시물과 정보를 볼 수 있습니다.
              </div>
            </label>
          </div>
        </div>

        <hr class="my-4">
        
        <!-- DM 수신 설정 -->
        <div class="settings-section mb-4">
          <h5 class="settings-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            DM 수신 설정
          </h5>
          
          <div class="setting-item">
            <label class="setting-label">
              <input type="radio" 
                     name="dm_permission" 
                     value="everyone" 
                     <?php echo ($user['dm_permission'] ?? 'everyone') === 'everyone' ? 'checked' : ''; ?>>
              <div class="setting-text">
                <strong>모든 사람</strong>
                <small>누구나 메시지를 보낼 수 있습니다</small>
              </div>
            </label>
          </div>
          
          <div class="setting-item">
            <label class="setting-label">
              <input type="radio" 
                     name="dm_permission" 
                     value="followers" 
                     <?php echo ($user['dm_permission'] ?? 'everyone') === 'followers' ? 'checked' : ''; ?>>
              <div class="setting-text">
                <strong>팔로워만</strong>
                <small>서로 팔로우 중인 사람만 메시지를 보낼 수 있습니다</small>
              </div>
            </label>
          </div>
        </div>

        <hr class="my-4">
        
        <div class="mb-3">
          <label class="form-label">닉네임</label>
          <input class="form-control" name="nickname" value="<?php echo htmlspecialchars($user['nickname']); ?>" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">사용자명</label>
          <div class="input-group">
            <span class="input-group-text">@</span>
            <input class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
          </div>
          <small class="form-text text-muted">
            오늘 남은 변경 횟수: <strong><?php echo $remaining_changes; ?></strong>회
          </small>
        </div>
        
        <div class="mb-3">
          <label class="form-label">이메일</label>
          <input class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
        </div>
        
        <div class="mb-4">
          <label class="form-label">자기소개</label>
          <textarea class="form-control" name="bio" rows="4" placeholder="자신을 소개해보세요..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
        </div>
        
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary">저장</button>
          <a href="user_profile.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-secondary">취소</a>
        </div>
      </form>
      
      <hr class="my-4">
      
      <div class="text-center">
        <a href="delete_account.php" class="text-danger text-decoration-none">
          <small>회원 탈퇴</small>
        </a>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css">

<style>
.settings-section {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  padding: 20px;
}

.settings-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 16px;
  color: var(--text-primary);
}

.setting-item {
  padding: 12px 0;
  border-bottom: 1px solid var(--border-color);
}

.setting-item:last-child {
  border-bottom: none;
}

.setting-label {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  width: 100%;
}

.setting-label input[type="radio"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
  flex-shrink: 0;
}

.setting-text {
  flex: 1;
}

.setting-text strong {
  display: block;
  color: var(--text-primary);
  margin-bottom: 4px;
  font-size: 15px;
}

.setting-text small {
  color: var(--text-secondary);
  font-size: 13px;
}

/* 기존 스타일 유지 */
#coverImagesContainer {
  display: grid;
  place-items: center;
  grid-template-columns: repeat(3, minmax(340px, 1fr));
  gap: 24px;
  width: 100%;
  max-width: 1200px; 
  margin: 1.5rem auto 0;
}

@media (min-width: 1025px) {
  #coverImagesContainer {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 1024px) {
  #coverImagesContainer {
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }
}

@media (max-width: 640px) {
  #coverImagesContainer {
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
  }
}

#headerSwiper {
  width: 100%;
  height: 250px;
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#headerSwiper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.header-preview-swiper {
  position: relative;
  margin-bottom: 1.5rem;
}

.header-controls {
  margin-top: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.cover-images-grid {
  display: grid;
  width: 100%;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  margin-top: 1.5rem;
}

.cover-item {
  position: relative;
  aspect-ratio: 16 / 9;
  border-radius: 12px;
  overflow: hidden;
  border: 2px solid var(--border-color);
  transition: transform 0.2s, border-color 0.2s;
}

.cover-item.active {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px rgba(86, 105, 254, 0.2);
}

.cover-item:hover {
  transform: scale(1.05);
}

.cover-item.dragging {
  opacity: 0.7;
  cursor: grabbing;
}

.cover-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.image-checkbox {
  position: absolute;
  top: 10px;
  left: 10px;
  width: 20px;
  height: 20px;
  cursor: pointer;
  z-index: 10;
}

.cover-actions {
  position: absolute;
  top: 8px;
  right: 8px;
  display: flex;
  gap: 8px;
  opacity: 0;
  transition: opacity 0.2s;
  z-index: 20;
}

.cover-item:hover .cover-actions {
  opacity: 1;
}

.btn-cover-action {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  padding: 0;
}

.btn-cover-action:hover {
  background: rgba(0, 0, 0, 0.9);
  transform: scale(1.1);
}

.btn-cover-action.btn-delete:hover {
  background: #dc3545;
}

.drag-handle {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background: rgba(0,0,0,0.6);
  color: white;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 12px;
  cursor: move;
  z-index: 15;
}

.active-badge {
  position: absolute;
  bottom: 8px;
  left: 8px;
  background: var(--primary-color);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.empty-covers {
  grid-column: 1 / -1;
  text-align: center;
  padding: 40px;
  color: var(--text-secondary);
}

.empty-covers svg {
  margin-bottom: 16px;
  opacity: 0.5;
}

.profile-img-preview-container {
  display: inline-block;
  position: relative;
}

.profile-img-preview {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  object-fit: cover;
  object-position: center;
  border: 5px solid var(--bg-primary);
  box-shadow: var(--shadow-md);
}

.toggle-checkbox {
  width: 40px;
  height: 24px;
  cursor: pointer;
  appearance: none;
  background: #ccc;
  border: none;
  border-radius: 12px;
  transition: background 0.3s;
  position: relative;
}

.toggle-checkbox:checked {
  background: #4c5bafff;
}

.toggle-checkbox::after {
  content: '';
  position: absolute;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  top: 2px;
  left: 2px;
  transition: left 0.3s;
}

.toggle-checkbox:checked::after {
  left: 18px;
}

.swiper-button-prev, .swiper-button-next {
  color: white;
  background: rgba(0,0,0,0.5);
  width: 45px;
  height: 45px;
  border-radius: 50%;
}

.swiper-button-prev:hover, .swiper-button-next:hover {
  background: rgba(0,0,0,0.8);
}

.swiper-pagination-bullet {
  background: rgba(255,255,255,0.6);
  opacity: 1;
}

.swiper-pagination-bullet-active {
  background: white;
}

.auto-dismiss {
  animation: fadeOut 0.5s ease-in-out 3s forwards;
}

@keyframes fadeOut {
  0% { opacity: 1; }
  100% { opacity: 0; display: none; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js"></script>
<script>
let swiper = null;
let autoplayEnabled = true;
let draggedItem = null;

function initSwiper() {
  const swiperEl = document.getElementById('headerSwiper');
  if (!swiperEl) return;
  
  swiper = new Swiper('#headerSwiper', {
    loop: true,
    autoplay: { delay: 5000, disableOnInteraction: false },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    pagination: { el: '.swiper-pagination', clickable: true },
    grabCursor: true,
  });
}

document.getElementById('autoplayToggle')?.addEventListener('click', function() {
  autoplayEnabled = this.checked;
  if (swiper) {
    if (autoplayEnabled) {
      swiper.autoplay.start();
      document.getElementById('autoplayStatus').textContent = '켜짐';
    } else {
      swiper.autoplay.stop();
      document.getElementById('autoplayStatus').textContent = '꺼짐';
    }
  }
});

document.querySelectorAll('.image-checkbox').forEach(checkbox => {
  checkbox.addEventListener('change', updateBatchButtons);
});

function updateBatchButtons() {
  const selectedCount = document.querySelectorAll('.image-checkbox:checked').length;
  document.getElementById('deleteBatchBtn').style.display = selectedCount > 0 ? 'block' : 'none';
  document.getElementById('deselectBtn').style.display = selectedCount > 0 ? 'block' : 'none';
}

document.getElementById('deleteBatchBtn')?.addEventListener('click', function() {
  const selected = Array.from(document.querySelectorAll('.image-checkbox:checked')).map(cb => cb.dataset.coverId);
  if (selected.length === 0 || !confirm(`${selected.length}개 이미지를 삭제하시겠습니까?`)) return;
  
  fetch('delete_covers_batch.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'cover_ids=' + selected.join(',')
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      showNotification(data.message, 'error');
    }
  })
  .catch(err => showNotification('삭제 실패', 'error'));
});

document.getElementById('deselectBtn')?.addEventListener('click', function() {
  document.querySelectorAll('.image-checkbox:checked').forEach(cb => cb.checked = false);
  updateBatchButtons();
});

document.getElementById('profileImageInput')?.addEventListener('change', function(e) {
  if (e.target.files && e.target.files[0]) {
    const reader = new FileReader();
    reader.onload = event => document.getElementById('profilePreview').src = event.target.result;
    reader.readAsDataURL(e.target.files[0]);
  }
});

function setActiveCover(coverId) {
  const formData = new FormData();
  formData.append('cover_id', coverId);
  
  fetch('set_active_cover.php', { method: 'POST', body: formData })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      showNotification(data.message, 'error');
    }
  })
  .catch(err => showNotification('변경 실패', 'error'));
}

function deleteCover(coverId) {
  if (!confirm('이 헤더 이미지를 삭제하시겠습니까?')) return;
  
  const formData = new FormData();
  formData.append('cover_id', coverId);
  
  fetch('delete_cover.php', { method: 'POST', body: formData })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      showNotification(data.message, 'error');
    }
  })
  .catch(err => showNotification('삭제 실패', 'error'));
}

document.addEventListener('dragstart', e => {
  if (e.target.closest('.cover-item')) {
    draggedItem = e.target.closest('.cover-item');
    draggedItem.classList.add('dragging');
  }
});

document.addEventListener('dragover', e => e.preventDefault());

document.addEventListener('drop', e => {
  e.preventDefault();
  const dropTarget = e.target.closest('.cover-item');
  if (dropTarget && draggedItem && draggedItem !== dropTarget) {
    const container = document.getElementById('coverImagesContainer');
    container.insertBefore(draggedItem, dropTarget);
    saveCoverOrder();
  }
});

document.addEventListener('dragend', () => draggedItem?.classList.remove('dragging'));

function saveCoverOrder() {
  const order = Array.from(document.querySelectorAll('.cover-item')).map((item, idx) => ({
    id: parseInt(item.dataset.coverId),
    order: idx
  }));
  
  fetch('update_cover_order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(order)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      updateSwiperOrder();
      showNotification(data.message || '순서가 변경되었습니다.', 'success');
    } else {
      showNotification(data.message || '순서 저장 실패', 'error');
    }
  })
  .catch(err => {
    console.error('Order save error:', err);
    showNotification('순서 저장에 실패했습니다', 'error');
  });
}

function updateSwiperOrder() {
  if (!swiper) return;
  
  const coverItems = Array.from(document.querySelectorAll('.cover-item'));
  const swiperWrapper = document.querySelector('#headerSwiper .swiper-wrapper');
  
  if (!swiperWrapper) return;
  
  swiperWrapper.innerHTML = '';
  
  coverItems.forEach(item => {
    const img = item.querySelector('img');
    if (img) {
      const slide = document.createElement('div');
      slide.className = 'swiper-slide';
      slide.innerHTML = `<img src="${img.src}" alt="header">`;
      swiperWrapper.appendChild(slide);
    }
  });
  
  swiper.update();
  swiper.slideTo(0);
}

function showNotification(message, type = 'success') {
  const alertDiv = document.createElement('div');
  alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} auto-dismiss`;
  alertDiv.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
  alertDiv.innerHTML = `${message}<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
  document.body.appendChild(alertDiv);
  setTimeout(() => alertDiv.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
  initSwiper();
  document.querySelectorAll('.auto-dismiss').forEach(alert => setTimeout(() => alert.remove(), 3500));
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>