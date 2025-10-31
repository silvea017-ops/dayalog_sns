<?php
// dayalog/public/pages/delete_account.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user']['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // 1. 사용자의 모든 게시물과 미디어 파일 가져오기
        $stmt = $pdo->prepare("SELECT image_path, media_type FROM posts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $posts = $stmt->fetchAll();
        
        // 게시물 이미지/비디오 삭제
        foreach($posts as $post) {
            if ($post['image_path']) {
                $file_path = dirname(__DIR__, 2) . '/public/' . $post['image_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
        
        // 2. 프로필 이미지 삭제 (기본 이미지가 아닌 경우)
        if ($_SESSION['user']['profile_img'] && 
            $_SESSION['user']['profile_img'] !== 'assets/images/sample.png') {
            $profile_path = dirname(__DIR__, 2) . '/public/' . $_SESSION['user']['profile_img'];
            if (file_exists($profile_path)) {
                unlink($profile_path);
            }
        }
        
        // 3. 커버 이미지들 삭제
        $stmt = $pdo->prepare("SELECT image_path FROM cover_images WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $covers = $stmt->fetchAll();
        
        foreach($covers as $cover) {
            if ($cover['image_path']) {
                $cover_path = dirname(__DIR__, 2) . '/public/' . $cover['image_path'];
                if (file_exists($cover_path)) {
                    unlink($cover_path);
                }
            }
        }
        
        // 4. 데이터베이스에서 관련 데이터 삭제
        
        // 커버 이미지 삭제
        $stmt = $pdo->prepare("DELETE FROM cover_images WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 댓글 삭제
        $stmt = $pdo->prepare("DELETE FROM comments WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 좋아요 삭제
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 알림 삭제 (보낸 알림 + 받은 알림)
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? OR from_user_id = ?");
        $stmt->execute([$user_id, $user_id]);
        
        // 팔로우 관계 삭제
        $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? OR following_id = ?");
        $stmt->execute([$user_id, $user_id]);
        
        // 차단 관계 삭제
        $stmt = $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? OR blocked_id = ?");
        $stmt->execute([$user_id, $user_id]);
        
        // 사용자명 변경 기록 삭제
        $stmt = $pdo->prepare("DELETE FROM username_history WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 게시물 삭제
        $stmt = $pdo->prepare("DELETE FROM posts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 사용자 삭제
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        // 세션 삭제
        session_unset();
        session_destroy();
        
        // 로그인 페이지로 리다이렉트
        header('Location: ' . BASE_URL . '/pages/login.php?deleted=1');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Account deletion error: " . $e->getMessage());
        $_SESSION['error_message'] = '회원 탈퇴 처리 중 오류가 발생했습니다.';
        header('Location: ' . BASE_URL . '/pages/profile.php');
        exit;
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="delete-account-card">
        <div class="text-center mb-4">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" class="mb-3">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <h3 class="text-danger">회원 탈퇴</h3>
        </div>
        
        <div class="alert alert-warning">
          <h5 class="alert-heading">⚠️ 주의사항</h5>
          <ul class="mb-0">
            <li>탈퇴 시 모든 게시물이 삭제됩니다.</li>
            <li>업로드한 모든 사진과 동영상이 삭제됩니다.</li>
            <li>모든 댓글과 좋아요가 삭제됩니다.</li>
            <li>팔로우 관계가 모두 해제됩니다.</li>
            <li>프로필 정보가 완전히 삭제됩니다.</li>
            <li><strong>탈퇴한 계정은 복구할 수 없습니다.</strong></li>
          </ul>
        </div>
        
        <?php if(isset($_SESSION['error_message'])): ?>
          <div class="alert alert-danger alert-dismissible fade show">
            <?php 
              echo htmlspecialchars($_SESSION['error_message']); 
              unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        
        <form method="post" onsubmit="return confirm('정말로 탈퇴하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.\n모든 데이터가 영구적으로 삭제됩니다.');">
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-danger">회원 탈퇴</button>
            <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="btn btn-outline-secondary">취소</a>
          </div>
        </form>
        
        <div class="mt-4 text-center">
          <small class="text-muted">
            탈퇴 관련 문의사항이 있으시면 <a href="#">고객센터</a>로 연락주세요.
          </small>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.delete-account-card {
  background: var(--bg-primary);
  border-radius: 12px;
  padding: 32px;
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-color);
}

.delete-account-card h3 {
  font-weight: 700;
  margin-bottom: 8px;
}

.delete-account-card .alert {
  text-align: left;
  margin-bottom: 24px;
}

.delete-account-card .alert ul {
  padding-left: 20px;
  margin-top: 12px;
}

.delete-account-card .alert li {
  margin-bottom: 8px;
}

.btn-danger {
  font-weight: 600;
  padding: 12px;
}

.btn-outline-secondary {
  font-weight: 600;
  padding: 12px;
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>