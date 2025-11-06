<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 설정 파일 로드
$settings_file = __DIR__ . '/settings.php';
if (file_exists($settings_file)) {
    require_once $settings_file;
    $favicon = getFaviconPath($pdo);
    $site_name = getSetting($pdo, 'site_name', 'Dayalog');
    $site_logo = getLogoPath($pdo);
} else {
    $favicon = 'assets/images/favicon.ico';
    $site_name = 'Dayalog';
    $site_logo = 'assets/images/logo.svg';
}


// DB 연결 먼저 확인
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config/db.php';
}

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/paths.php';
}

$theme = isset($_COOKIE['dayalog_theme']) ? $_COOKIE['dayalog_theme'] : 'light';

$show_all_tab = true;
if (isset($_SESSION['user'])) {
    $show_all_tab = isset($_SESSION['user']['show_all_tab']) ? (bool)$_SESSION['user']['show_all_tab'] : true;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isIndexPage = $currentPage === 'index.php';
$isFollowingPage = $currentPage === 'following.php';
$isSearchPage = $currentPage === 'search.php';

$show_splash = !isset($_SESSION['visited']);
if ($show_splash) {
    $_SESSION['visited'] = true;
}
?>

<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- 파비콘 경로 수정 -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($favicon); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($favicon); ?>">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($favicon); ?>">
    
    <style>
         html {
        visibility: visible;
        opacity: 1;
    }
    
    body[data-theme="light"] {
        --bg-primary: #ffffff;
        --bg-secondary: #f8f9fa;
        --bg-hover: #f1f3f5;
        --text-primary: #212529;
        --text-secondary: #6c757d;
        --border-color: #dee2e6;
        --primary-color: #667eea;
        --primary-hover: #5568d3;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    body[data-theme="dark"] {
        --bg-primary: #1a1d23;
        --bg-secondary: #22262e;
        --bg-hover: #2a2f38;
        --text-primary: #e9ecef;
        --text-secondary: #adb5bd;
        --border-color: #373d47;
        --primary-color: #667eea;
        --primary-hover: #5568d3;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.2);
    }
    
    * {
        transition: none !important;
    }
    
    body {
        background: var(--bg-primary);
        color: var(--text-primary);
        margin: 0;
        padding: 0;
    }
    
    body * {
        transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease !important;
    }
    
    .page-wrapper {
        opacity: 1;
        visibility: visible;
    }
    
    /* 로고 크기 고정 */
    .brand-logo {
        width: 32px !important;
        height: 32px !important;
        object-fit: contain;
    }
    
    /* 이미지 깜빡임 방지 */
    img {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
        body[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-hover: #f1f3f5;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --primary-color: #667eea;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        body[data-theme="dark"] {
            --bg-primary: #1a1d23;
            --bg-secondary: #22262e;
            --bg-hover: #2a2f38;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --border-color: #373d47;
            --primary-color: #667eea;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: none !important;
        }
        
        .page-wrapper {
            opacity: 1;
        }
    </style>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
    <script>
        (function() {
            const theme = document.cookie.split('; ').find(row => row.startsWith('dayalog_theme='));
            const currentTheme = theme ? theme.split('=')[1] : 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);
        })();
    </script>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
<div class="page-wrapper">
<?php if($show_splash): ?>
<!-- 로딩 스플래시 스크린 -->
<div id="loadingSplash" class="loading-splash">
  <div class="loading-content">
    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($site_logo); ?>" class="logo-animation" alt="<?php echo htmlspecialchars($site_name); ?>" width="80" height="80">
    <h2 class="loading-title"><?php echo htmlspecialchars($site_name); ?></h2>
    <div class="loading-spinner"></div>
  </div>
</div>

<style>
.loading-splash {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--bg-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  transition: opacity 0.5s ease-in-out;
}

.loading-splash.fade-out {
  opacity: 0;
  pointer-events: none;
}

.loading-content {
  text-align: center;
  animation: fadeInUp 0.6s ease-out;
}

.logo-animation {
  animation: pulse 2s ease-in-out infinite;
  margin-bottom: 20px;
}

.loading-title {
  font-size: 32px;
  font-weight: 700;
  background: linear-gradient(135deg, var(--primary-color), #667eea);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 30px;
  animation: fadeInUp 0.6s ease-out 0.2s backwards;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--border-color);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite, fadeInUp 0.6s ease-out 0.4s backwards;
  margin: 0 auto;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.8;
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>

<script>
window.addEventListener('load', function() {
  setTimeout(function() {
    const splash = document.getElementById('loadingSplash');
    if (splash) {
      splash.classList.add('fade-out');
      setTimeout(function() {
        splash.remove();
      }, 500);
    }
  }, 800);
});
</script>
<?php endif; ?>

<!-- 새로운 트위터 스타일 네비게이션 -->
<nav class="modern-navbar">
  <div class="navbar-container">
    <!-- 로고 영역 -->
  <div class="navbar-section navbar-brand-section">
  <a class="brand-link" href="<?php echo BASE_URL; ?>/pages/<?php echo (!isset($_SESSION['user']) || $show_all_tab) ? 'index.php' : 'following.php'; ?>">
    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="brand-logo">
    <strong class="brand-text"><?php echo htmlspecialchars($site_name); ?></strong>
  </a>
</div>
    
    <?php if(isset($_SESSION['user'])): ?>
    <!-- 왼쪽 탭 네비게이션 -->
    <div class="navbar-section navbar-left-section">
      <ul class="nav-tabs-list">
        <?php if($show_all_tab): ?>
        <li class="nav-tab-item">
          <a class="nav-tab-link <?php echo $isIndexPage ? 'active' : ''; ?>" 
             href="<?php echo BASE_URL; ?>/pages/index.php">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            </svg>
            <span class="nav-tab-text">전체</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-tab-item">
          <a class="nav-tab-link <?php echo $isFollowingPage ? 'active' : ''; ?>" 
             href="<?php echo BASE_URL; ?>/pages/following.php">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="nav-tab-text">팔로우중</span>
          </a>
        </li>
      </ul>
    </div>
    <?php endif; ?>
    
    <!-- 오른쪽 아이콘 영역 -->
    <div class="navbar-section navbar-actions-section">
      <?php if(isset($_SESSION['user'])): ?>
        <!-- 데스크톱 검색창 -->
        <form method="get" action="<?php echo BASE_URL; ?>/pages/search.php" class="desktop-search-form">
          <div class="search-box">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="search-icon">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" name="q" class="search-input" placeholder="검색..." 
                   value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
          </div>
        </form>
        
        <!-- 모바일 검색 버튼 -->
        <a href="<?php echo BASE_URL; ?>/pages/search.php" class="nav-icon-btn mobile-search-btn <?php echo $isSearchPage ? 'active' : ''; ?>">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
        </a>
        
        <!-- 알림 버튼 -->
        <a href="<?php echo BASE_URL; ?>/pages/notifications.php" class="nav-icon-btn notification-btn">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
        </a>
        
        <!-- 설정 버튼 -->
        <button class="nav-icon-btn settings-btn" id="settingsBtn">
          <i class="fas fa-cog"></i>
        </button>
        
        <!-- 프로필 이미지 -->
        <button class="profile-btn" id="profileBtn">
          <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>" 
               alt="profile" class="profile-img">
        </button>
      <?php else: ?>
        <a class="btn btn-outline-primary btn-sm me-2" href="<?php echo BASE_URL; ?>/pages/login.php">로그인</a>
        <a class="btn btn-primary btn-sm" href="<?php echo BASE_URL; ?>/pages/register.php">회원가입</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php if(isset($_SESSION['user'])): ?>
<!-- 설정 드롭다운 메뉴 -->
<div class="dropdown-menu-custom" id="settingsMenu">
  <a class="dropdown-item-custom" href="<?php echo BASE_URL; ?>/api/theme_toggle.php">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="5"></circle>
      <line x1="12" y1="1" x2="12" y2="3"></line>
      <line x1="12" y1="21" x2="12" y2="23"></line>
      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
      <line x1="1" y1="12" x2="3" y2="12"></line>
      <line x1="21" y1="12" x2="23" y2="12"></line>
      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
      <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
    </svg>
    테마 변경
  </a>
  <a class="dropdown-item-custom" href="#" onclick="toggleAllTab(event)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <?php if($show_all_tab): ?>
        <path d="M17 14l4-4-4-4M7 10l-4 4 4 4"></path>
      <?php else: ?>
        <path d="M7 14l-4-4 4-4M17 10l4 4-4 4"></path>
      <?php endif; ?>
    </svg>
    <?php echo $show_all_tab ? '전체 탭 숨기기' : '전체 탭 보이기'; ?>
  </a>
  <?php if(isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin']): ?>
  <div class="dropdown-divider-custom"></div>
  <a class="dropdown-item-custom" href="<?php echo BASE_URL; ?>/pages/admin_settings.php">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="3"></circle>
      <path d="M12 1v6m0 6v6"></path>
      <path d="m4.93 4.93 4.24 4.24m5.66 5.66 4.24 4.24"></path>
      <path d="m19.07 4.93-4.24 4.24m-5.66 5.66-4.24 4.24"></path>
    </svg>
    사이트 설정
  </a>
  <?php endif; ?>
</div>

<!-- 프로필 드롭다운 메뉴 -->
<div class="dropdown-menu-custom" id="profileMenu">
  <div class="profile-menu-header">
    <img src="<?php echo getProfileImageUrl($_SESSION['user']['profile_img']); ?>" 
         alt="profile" class="profile-menu-img">
    <div class="profile-menu-info">
      <div class="profile-menu-name"><?php echo htmlspecialchars($_SESSION['user']['nickname']); ?></div>
      <div class="profile-menu-username">@<?php echo htmlspecialchars($_SESSION['user']['username']); ?></div>
    </div>
  </div>
  <div class="dropdown-divider-custom"></div>
  <a class="dropdown-item-custom" href="<?php echo BASE_URL; ?>/pages/user_profile.php?id=<?php echo $_SESSION['user']['user_id']; ?>">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
      <circle cx="12" cy="7" r="4"></circle>
    </svg>
    내 프로필
  </a>
  <a class="dropdown-item-custom" href="<?php echo BASE_URL; ?>/pages/profile.php">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
    </svg>
    프로필 편집
  </a>
  <a class="dropdown-item-custom" href="<?php echo BASE_URL; ?>/pages/follow_requests.php">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="8.5" cy="7" r="4"></circle>
      <line x1="20" y1="8" x2="20" y2="14"></line>
      <line x1="23" y1="11" x2="17" y2="11"></line>
    </svg>
    팔로우 요청
  </a>
  <div class="dropdown-divider-custom"></div>
  <a class="dropdown-item-custom text-danger" href="<?php echo BASE_URL; ?>/pages/logout.php">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
      <polyline points="16 17 21 12 16 7"></polyline>
      <line x1="21" y1="12" x2="9" y2="12"></line>
    </svg>
    로그아웃
  </a>
</div>
<?php endif; ?>

<style>
/* 모던 네비게이션 바 스타일 */
.modern-navbar {
  position: sticky;
  top: 0;
  z-index: 1030;
  background: var(--bg-primary);
  border-bottom: 1px solid var(--border-color);
  box-shadow: var(--shadow-sm);
  backdrop-filter: blur(10px);
}

.navbar-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 60px;
  gap: 20px;
}

.navbar-section {
  display: flex;
  align-items: center;
}

/* 브랜드 영역 */
.navbar-brand-section {
  flex-shrink: 0;
}

.brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: var(--text-primary);
  font-size: 20px;
  font-weight: 700;
  transition: opacity 0.2s;
}

.brand-link:hover {
  opacity: 0.8;
}

.brand-logo {
  width: 32px;
  height: 32px;
}

.brand-text {
  display: none;
}

/* 중앙 네비게이션 */
.navbar-left-section {
  display: flex;
  align-items: center;
  gap: 8px;
}

.nav-tabs-list {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
  gap: 4px;
}

.nav-tab-item {
  margin: 0;
}

.nav-tab-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 12px;
  color: var(--text-secondary);
  text-decoration: none;
  border-radius: 24px;
  transition: all 0.2s;
  font-weight: 500;
  min-width: 44px;
  height: 44px;
}

.nav-tab-link:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.nav-tab-link.active {
  color: var(--primary-color);
  background: rgba(102, 126, 234, 0.1);
}

.nav-tab-text {
  display: none;
}

/* 데스크톱 검색창 */
.desktop-search-form {
  display: none;
  max-width: 300px;
}

.search-box {
  display: flex;
  align-items: center;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 24px;
  padding: 8px 16px;
  gap: 8px;
  transition: all 0.2s;
}

.search-box:focus-within {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  background: var(--bg-primary);
}

.search-icon {
  color: var(--text-secondary);
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  color: var(--text-primary);
  font-size: 15px;
}

.search-input::placeholder {
  color: var(--text-secondary);
}

/* 액션 버튼들 */
.navbar-actions-section {
  gap: 8px;
  display: flex;
  align-items: center;
  margin-left: auto;
}

.nav-icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: transparent;
  border: none;
  color: var(--text-primary);
  cursor: pointer;
  transition: all 0.2s;
  font-size: 20px;
}

.nav-icon-btn i {
  line-height: 1;
}

.nav-icon-btn:hover {
  background: var(--bg-hover);
}

.nav-icon-btn.active {
  color: var(--primary-color);
}

.mobile-search-btn {
  display: flex;
}

.notification-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background: #e0245e;
  color: white;
  border-radius: 10px;
  padding: 2px 6px;
  font-size: 11px;
  font-weight: 600;
  min-width: 18px;
  text-align: center;
  line-height: 1;
}

.profile-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  padding: 0;
  cursor: pointer;
  overflow: hidden;
  background: var(--bg-secondary);
  transition: opacity 0.2s;
}

.profile-btn:hover {
  opacity: 0.8;
}

.profile-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* 드롭다운 메뉴 */
.dropdown-menu-custom {
  position: fixed;
  top: 70px;
  width: 280px;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  display: none;
  overflow: hidden;
  z-index: 1031;
}

#settingsMenu {
  right: auto;
}

#profileMenu {
  right: auto;
}

.dropdown-menu-custom.show {
  display: block;
  animation: dropdownFadeIn 0.15s ease-out;
}

@keyframes dropdownFadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.profile-menu-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
}

.profile-menu-img {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}

.profile-menu-info {
  flex: 1;
  min-width: 0;
}

.profile-menu-name {
  font-weight: 600;
  color: var(--text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-menu-username {
  font-size: 14px;
  color: var(--text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-item-custom {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  color: var(--text-primary);
  text-decoration: none;
  transition: background 0.2s;
}

.dropdown-item-custom:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.dropdown-item-custom.text-danger {
  color: #dc3545;
}

.dropdown-item-custom.text-danger:hover {
  background: rgba(220, 53, 69, 0.1);
  color: #dc3545;
}

.dropdown-divider-custom {
  height: 1px;
  background: var(--border-color);
  margin: 8px 0;
}

/* 태블릿 */
@media (min-width: 768px) {
  .brand-text {
    display: inline;
  }
  
  .navbar-left-section {
    gap: 16px;
  }
  
  .nav-tabs-list {
    gap: 8px;
  }
  
  .nav-tab-link {
    padding: 8px 16px;
  }
  
  .nav-tab-text {
    display: inline;
  }
  
  .mobile-search-btn {
    display: none;
  }
  
  .desktop-search-form {
    display: block;
  }
  
  .navbar-container {
    padding: 0 24px;
  }
}

/* 데스크톱 */
@media (min-width: 1024px) {
  .desktop-search-form {
    max-width: 400px;
  }
  
  .navbar-actions-section {
    gap: 12px;
  }
  
  .nav-icon-btn {
    width: 44px;
    height: 44px;
  }
}

/* 오버레이 */
.dropdown-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: transparent;
  z-index: 1029;
  display: none;
}

.dropdown-overlay.show {
  display: block;
}
</style>

<script>
// 드롭다운 메뉴 토글
document.addEventListener('DOMContentLoaded', function() {
  const settingsBtn = document.getElementById('settingsBtn');
  const settingsMenu = document.getElementById('settingsMenu');
  const profileBtn = document.getElementById('profileBtn');
  const profileMenu = document.getElementById('profileMenu');
  
  // 오버레이 생성
  const overlay = document.createElement('div');
  overlay.className = 'dropdown-overlay';
  document.body.appendChild(overlay);
  
  function closeAllMenus() {
    if (settingsMenu) settingsMenu.classList.remove('show');
    if (profileMenu) profileMenu.classList.remove('show');
    overlay.classList.remove('show');
  }
  
  function positionMenu(button, menu) {
    const rect = button.getBoundingClientRect();
    const menuWidth = 280;
    const windowWidth = window.innerWidth;
    
    // 버튼 아래에 위치
    menu.style.top = (rect.bottom + 8) + 'px';
    
    // 버튼의 오른쪽 끝을 기준으로 메뉴의 오른쪽 끝// 버튼의 오른쪽 끝을 기준으로 메뉴의 오른쪽 끝 정렬
    let rightPosition = windowWidth - rect.right;
    
    // 화면 왼쪽을 벗어나지 않도록
    const leftEdge = windowWidth - rightPosition - menuWidth;
    if (leftEdge < 16) {
      rightPosition = windowWidth - menuWidth - 16;
    }
    
    // 화면 오른쪽을 벗어나지 않도록
    if (rightPosition < 16) {
      rightPosition = 16;
    }
    
    menu.style.right = rightPosition + 'px';
    menu.style.left = 'auto';
  }
  
  if (settingsBtn && settingsMenu) {
    settingsBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = settingsMenu.classList.contains('show');
      closeAllMenus();
      if (!isOpen) {
        positionMenu(settingsBtn, settingsMenu);
        settingsMenu.classList.add('show');
        overlay.classList.add('show');
      }
    });
  }
  
  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = profileMenu.classList.contains('show');
      closeAllMenus();
      if (!isOpen) {
        positionMenu(profileBtn, profileMenu);
        profileMenu.classList.add('show');
        overlay.classList.add('show');
      }
    });
  }
  
  overlay.addEventListener('click', closeAllMenus);
  
  document.addEventListener('click', function(e) {
    if (settingsMenu && profileMenu) {
      if (!settingsMenu.contains(e.target) && !profileMenu.contains(e.target)) {
        closeAllMenus();
      }
    }
  });
  
  // 창 크기 변경 시 메뉴 위치 재조정
  window.addEventListener('resize', function() {
    if (settingsMenu && settingsMenu.classList.contains('show')) {
      positionMenu(settingsBtn, settingsMenu);
    }
    if (profileMenu && profileMenu.classList.contains('show')) {
      positionMenu(profileBtn, profileMenu);
    }
  });
});

// 전체 탭 토글
function toggleAllTab(event) {
  event.preventDefault();
  
  fetch('<?php echo BASE_URL; ?>/api/toggle_all_tab.php', {
    method: 'POST'
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const currentPage = '<?php echo $currentPage; ?>';
      
      if (!data.show_all_tab) {
        if (currentPage === 'index.php') {
          window.location.href = '<?php echo BASE_URL; ?>/pages/following.php';
        } else {
          location.reload();
        }
      } else {
        location.reload();
      }
    } else {
      alert(data.message || '설정 변경에 실패했습니다.');
    }
  })
  .catch(err => {
    console.error('Toggle error:', err);
    alert('설정 변경 중 오류가 발생했습니다.');
  });
}
</script>

<?php if(isset($_SESSION['user'])): ?>
<script>
// 알림 개수 실시간 업데이트
function updateNotificationCount() {
  fetch('<?php echo BASE_URL; ?>/pages/get_notification_count.php')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const badge = document.getElementById('notificationBadge');
        const totalCount = data.unread_count + data.follow_request_count;
        
        if (totalCount > 0) {
          badge.textContent = totalCount > 99 ? '99+' : totalCount;
          badge.style.display = 'block';
        } else {
          badge.style.display = 'none';
        }
      }
    })
    .catch(err => console.error('Notification count error:', err));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', updateNotificationCount);
} else {
  updateNotificationCount();
}
setInterval(updateNotificationCount, 30000);
</script>
<?php endif; ?>

<main class="container py-4">