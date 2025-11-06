<?php
/**
 * 관리자 설정 페이지 (수정본)
 */

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

$success_message = '';
$error_message = '';

// 설정값 가져오기 함수
function getSettingValue($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

// 설정값 저장 함수 (수정됨)
function saveSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $result = $stmt->execute([$key, $value, $value]);
        
        if (!$result) {
            error_log("Failed to save setting: $key = $value");
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Setting save error for $key: " . $e->getMessage());
        throw $e;
    }
}

// 모든 사용자 가져오기
$stmt = $pdo->query("SELECT user_id, username, nickname FROM users ORDER BY nickname ASC");
$all_users = $stmt->fetchAll();

// 커스텀 탭 가져오기
$stmt = $pdo->query("SELECT * FROM custom_tabs ORDER BY tab_order ASC, tab_id ASC");
$custom_tabs = $stmt->fetchAll();

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 커스텀 탭 추가
        if (isset($_POST['action']) && $_POST['action'] === 'add_tab') {
            $tab_name = trim($_POST['tab_name']);
            $tab_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $tab_name));
            $tab_icon = trim($_POST['tab_icon'] ?? '');
            $allowed_users = isset($_POST['allowed_users']) ? implode(',', $_POST['allowed_users']) : '';
            
            $stmt = $pdo->prepare("
                INSERT INTO custom_tabs (tab_name, tab_slug, tab_icon, allowed_user_ids, tab_order) 
                VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(tab_order), 0) + 1 FROM custom_tabs t2))
            ");
            $stmt->execute([$tab_name, $tab_slug, $tab_icon, $allowed_users]);
            
            $pdo->commit();
            $success_message = '탭이 추가되었습니다.';
            
            // 탭 목록 새로고침
            $stmt = $pdo->query("SELECT * FROM custom_tabs ORDER BY tab_order ASC, tab_id ASC");
            $custom_tabs = $stmt->fetchAll();
        }
        // 커스텀 탭 삭제
        elseif (isset($_POST['action']) && $_POST['action'] === 'delete_tab') {
            $tab_id = intval($_POST['tab_id']);
            $stmt = $pdo->prepare("DELETE FROM custom_tabs WHERE tab_id = ?");
            $stmt->execute([$tab_id]);
            
            $pdo->commit();
            $success_message = '탭이 삭제되었습니다.';
            
            // 탭 목록 새로고침
            $stmt = $pdo->query("SELECT * FROM custom_tabs ORDER BY tab_order ASC, tab_id ASC");
            $custom_tabs = $stmt->fetchAll();
        }
        // 커스텀 탭 토글
        elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_tab') {
            $tab_id = intval($_POST['tab_id']);
            $stmt = $pdo->prepare("UPDATE custom_tabs SET is_active = NOT is_active WHERE tab_id = ?");
            $stmt->execute([$tab_id]);
            
            $pdo->commit();
            $success_message = '탭 상태가 변경되었습니다.';
            
            // 탭 목록 새로고침
            $stmt = $pdo->query("SELECT * FROM custom_tabs ORDER BY tab_order ASC, tab_id ASC");
            $custom_tabs = $stmt->fetchAll();
        }
        // 메인 설정 저장
        else {
            // 사이트 기본 설정
            if (isset($_POST['site_name'])) {
                saveSetting($pdo, 'site_name', trim($_POST['site_name']));
            }
            
            if (isset($_POST['site_description'])) {
                saveSetting($pdo, 'site_description', trim($_POST['site_description']));
            }
            
            // 로고 업로드
            if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                $filename = $_FILES['site_logo']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $upload_dir = dirname(__DIR__, 2) . '/assets/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $new_filename = 'logo.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_path)) {
                        saveSetting($pdo, 'site_logo', 'assets/images/' . $new_filename);
                    }
                }
            }
            
            // 파비콘 업로드
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg'];
                $filename = $_FILES['favicon']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $upload_dir = dirname(__DIR__, 2) . '/assets/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $new_filename = 'favicon.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['favicon']['tmp_name'], $upload_path)) {
                        saveSetting($pdo, 'favicon_path', 'assets/images/' . $new_filename);
                    }
                }
            }
            
            // 배너 이미지 업로드
            if (isset($_FILES['site_banner']) && $_FILES['site_banner']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['site_banner']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $upload_dir = dirname(__DIR__, 2) . '/assets/images/';
                    $new_filename = 'banner.' . $ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['site_banner']['tmp_name'], $upload_path)) {
                        saveSetting($pdo, 'site_banner', 'assets/images/' . $new_filename);
                    }
                }
            }
            
            // 테마 색상
            if (isset($_POST['primary_color'])) {
                saveSetting($pdo, 'primary_color', trim($_POST['primary_color']));
            }
            
            if (isset($_POST['secondary_color'])) {
                saveSetting($pdo, 'secondary_color', trim($_POST['secondary_color']));
            }
            
            // 회원가입 인증코드 설정
            saveSetting($pdo, 'require_invite_code', isset($_POST['require_invite_code']) ? '1' : '0');
            
            if (isset($_POST['invite_code'])) {
                saveSetting($pdo, 'invite_code', trim($_POST['invite_code']));
            }
            
            // 사이트 공개 여부
            saveSetting($pdo, 'site_public', isset($_POST['site_public']) ? '1' : '0');
            
            // 회원가입 허용 여부
            saveSetting($pdo, 'allow_registration', isset($_POST['allow_registration']) ? '1' : '0');
            
            // 게시물 최대 이미지 수
            if (isset($_POST['max_post_images'])) {
                $max_images = max(1, min(10, intval($_POST['max_post_images'])));
                saveSetting($pdo, 'max_post_images', $max_images);
            }
            
            // 게시물 최대 글자수
            if (isset($_POST['max_post_length'])) {
                $max_length = max(100, min(5000, intval($_POST['max_post_length'])));
                saveSetting($pdo, 'max_post_length', $max_length);
            }
            
            $pdo->commit();
            $success_message = '설정이 저장되었습니다.';
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = '설정 저장 중 오류가 발생했습니다: ' . $e->getMessage();
        error_log("Admin settings error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
}

// 현재 설정값 가져오기
$site_name = getSettingValue($pdo, 'site_name', 'Dayalog');
$site_description = getSettingValue($pdo, 'site_description', '일상을 공유하는 감성 SNS');
$site_logo = getSettingValue($pdo, 'site_logo', 'assets/images/logo.svg');
$favicon_path = getSettingValue($pdo, 'favicon_path', 'assets/images/favicon.ico');
$site_banner = getSettingValue($pdo, 'site_banner', '');
$primary_color = getSettingValue($pdo, 'primary_color', '#667eea');
$secondary_color = getSettingValue($pdo, 'secondary_color', '#764ba2');
$require_invite_code = getSettingValue($pdo, 'require_invite_code', '0');
$invite_code = getSettingValue($pdo, 'invite_code', '');
$site_public = getSettingValue($pdo, 'site_public', '1');
$allow_registration = getSettingValue($pdo, 'allow_registration', '1');
$max_post_images = getSettingValue($pdo, 'max_post_images', '8');
$max_post_length = getSettingValue($pdo, 'max_post_length', '1000');

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="admin-header mb-4">
                <h2>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6"></path>
                        <path d="m4.93 4.93 4.24 4.24m5.66 5.66 4.24 4.24"></path>
                        <path d="m19.07 4.93-4.24 4.24m-5.66 5.66-4.24 4.24"></path>
                    </svg>
                    사이트 관리 설정
                </h2>
                <p class="text-muted">사이트의 전반적인 설정을 관리합니다</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- 커스텀 탭 관리 섹션 (별도 폼들) -->
            <div class="settings-section">
                <h4 class="section-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                    커스텀 탭 관리
                </h4>
                
                <div class="alert alert-info">
                    <strong>💡 커스텀 탭이란?</strong><br>
                    공지사항, 이벤트 등 특정 목적의 게시물만 모아볼 수 있는 별도 탭을 생성할 수 있습니다.<br>
                    지정된 사용자의 게시물만 해당 탭에 표시됩니다.
                </div>
                
                <!-- 기존 탭 목록 -->
                <?php if (!empty($custom_tabs)): ?>
                <div class="mb-4">
                    <h6 class="mb-3">기존 탭 목록</h6>
                    <div class="list-group">
                        <?php foreach ($custom_tabs as $tab): 
                            $allowed_user_ids = $tab['allowed_user_ids'] ? explode(',', $tab['allowed_user_ids']) : [];
                            $user_names = [];
                            foreach ($allowed_user_ids as $uid) {
                                foreach ($all_users as $u) {
                                    if ($u['user_id'] == $uid) {
                                        $user_names[] = $u['nickname'] . ' (@' . $u['username'] . ')';
                                        break;
                                    }
                                }
                            }
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php if ($tab['tab_icon']): ?>
                                            <span style="font-size: 20px; margin-right: 8px;"><?php echo htmlspecialchars($tab['tab_icon']); ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($tab['tab_name']); ?>
                                        <?php if (!$tab['is_active']): ?>
                                            <span class="badge bg-secondary">비활성</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">활성</span>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        슬러그: <code><?php echo htmlspecialchars($tab['tab_slug']); ?></code>
                                    </small>
                                    <?php if (!empty($user_names)): ?>
                                        <div class="mt-2">
                                            <small><strong>게시 가능 사용자:</strong> <?php echo implode(', ', $user_names); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_tab">
                                        <input type="hidden" name="tab_id" value="<?php echo $tab['tab_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="<?php echo $tab['is_active'] ? '비활성화' : '활성화'; ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                        <input type="hidden" name="action" value="delete_tab">
                                        <input type="hidden" name="tab_id" value="<?php echo $tab['tab_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 새 탭 추가 (별도 폼) -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">새 탭 추가</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_tab">
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">탭 이름 *</label>
                                    <input type="text" name="tab_name" class="form-control" 
                                           placeholder="예: 공지사항" required>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">아이콘 (이모지)</label>
                                    <input type="text" name="tab_icon" class="form-control" 
                                           placeholder="📢" maxlength="10">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">게시 가능 사용자 *</label>
                                    <select name="allowed_users[]" class="form-select" multiple size="3" required>
                                        <?php foreach ($all_users as $user): ?>
                                            <option value="<?php echo $user['user_id']; ?>">
                                                <?php echo htmlspecialchars($user['nickname']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Ctrl/Cmd 키를 누른 채 여러 사용자 선택 가능</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    탭 추가
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 메인 설정 폼 -->
            <form method="POST" enctype="multipart/form-data" id="mainSettingsForm">
                <!-- 기본 설정 -->
                <div class="settings-section">
                    <h4 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        </svg>
                        기본 설정
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">사이트 이름</label>
                            <input type="text" name="site_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($site_name); ?>" required>
                            <small class="text-muted">헤더 및 모든 페이지에 표시됩니다</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">사이트 설명</label>
                            <input type="text" name="site_description" class="form-control" 
                                   value="<?php echo htmlspecialchars($site_description); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">로고 이미지</label>
                            <div class="image-upload-box">
                                <div class="current-image mb-2">
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($site_logo); ?>" 
                                         alt="Current Logo" style="max-width: 100px; max-height: 100px;">
                                </div>
                                <input type="file" name="site_logo" class="form-control" accept="image/*">
                                <small class="text-muted">SVG, PNG, JPG, GIF 파일 (권장: 정사각형)</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">파비콘</label>
                            <div class="image-upload-box">
                                <div class="current-image mb-2">
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($favicon_path); ?>" 
                                         alt="Current Favicon" style="max-width: 64px; max-height: 64px;">
                                </div>
                                <input type="file" name="favicon" class="form-control" accept="image/*,.ico">
                                <small class="text-muted">ICO, PNG, SVG 파일 (권장: 32x32px)</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">배너 이미지 (선택)</label>
                            <div class="image-upload-box">
                                <?php if ($site_banner): ?>
                                    <div class="current-image mb-2">
                                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($site_banner); ?>" 
                                             alt="Current Banner" style="max-width: 200px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="site_banner" class="form-control" accept="image/*">
                                <small class="text-muted">PNG, JPG, GIF 파일 (권장: 1200x400px)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 테마 색상 -->
                <div class="settings-section">
                    <h4 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 2a7 7 0 1 0 10 10"></path>
                        </svg>
                        테마 색상
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">주요 색상 (Primary Color)</label>
                            <div class="color-picker-group">
                                <input type="color" name="primary_color" class="form-control form-control-color" 
                                       value="<?php echo htmlspecialchars($primary_color); ?>">
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($primary_color); ?>" readonly>
                            </div>
                            <small class="text-muted">버튼, 링크 등에 사용됩니다</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">보조 색상 (Secondary Color)</label>
                            <div class="color-picker-group">
                                <input type="color" name="secondary_color" class="form-control form-control-color" 
                                       value="<?php echo htmlspecialchars($secondary_color); ?>">
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($secondary_color); ?>" readonly>
                            </div>
                            <small class="text-muted">그라데이션 등에 사용됩니다</small>
                        </div>
                    </div>
                    
                    <div class="color-preview mt-3">
                        <div class="preview-box" style="background: <?php echo htmlspecialchars($primary_color); ?>;">
                            Primary Color
                        </div>
                        <div class="preview-box" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($primary_color); ?>, <?php echo htmlspecialchars($secondary_color); ?>);">
                            Gradient
                        </div>
                    </div>
                </div>

                <!-- 회원가입 설정 -->
                <div class="settings-section">
                    <h4 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        회원가입 설정
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="allow_registration" 
                                       value="1" id="allowRegistration" 
                                       <?php echo $allow_registration == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allowRegistration">
                                    회원가입 허용
                                </label>
                            </div>
                            <small class="text-muted">비활성화하면 신규 가입이 불가능합니다</small>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="require_invite_code" 
                                       value="1" id="requireInviteCode" 
                                       <?php echo $require_invite_code == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requireInviteCode">
                                    초대 코드 필요
                                </label>
                            </div>
                            <small class="text-muted">활성화하면 초대 코드가 필요합니다</small>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">초대 코드</label>
                            <input type="text" name="invite_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($invite_code); ?>" 
                                   placeholder="예: DAYALOG2025">
                            <small class="text-muted">회원가입 시 입력해야 하는 코드입니다</small>
                        </div>
                    </div>
                </div>

                <!-- 게시물 설정 -->
                <div class="settings-section">
                    <h4 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        게시물 설정
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">최대 이미지 수</label>
                            <input type="number" name="max_post_images" class="form-control" 
                                   value="<?php echo htmlspecialchars($max_post_images); ?>" 
                                   min="1" max="10">
                            <small class="text-muted">게시물당 업로드 가능한 최대 이미지 수 (1-10)</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">최대 글자수</label>
                            <input type="number" name="max_post_length" class="form-control" 
                                   value="<?php echo htmlspecialchars($max_post_length); ?>" 
                                   min="100" max="5000" step="100">
                            <small class="text-muted">게시물 텍스트 최대 길이 (100-5000)</small>
                        </div>
                    </div>
                </div>

                <!-- 접근 설정 -->
                <div class="settings-section">
                    <h4 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        접근 설정
                    </h4>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="site_public" 
                                       value="1" id="sitePublic" 
                                       <?php echo $site_public == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sitePublic">
                                    사이트 공개
                                </label>
                            </div>
                            <small class="text-muted">비활성화하면 로그인한 사용자만 접근 가능합니다</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn btn-secondary">취소</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        설정 저장
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.admin-header {
    padding-bottom: 16px;
    border-bottom: 2px solid var(--border-color);
}

.admin-header h2 {
    margin: 0;
    color: var(--text-primary);
    font-weight: 700;
}

.settings-section {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
}

.image-upload-box {
    padding: 16px;
    background: var(--bg-secondary);
    border-radius: 8px;
}

.current-image img {
    border-radius: 8px;
    border: 1px solid var(--border-color);
    padding: 4px;
    background: var(--bg-primary);
}

.color-picker-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.form-control-color {
    width: 60px;
    height: 44px;
    padding: 4px;
}

.color-preview {
    display: flex;
    gap: 12px;
}

.preview-box {
    flex: 1;
    height: 80px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color, #764ba2));
    border: none;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.list-group-item {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    margin-bottom: 8px;
    border-radius: 8px !important;
}

.card {
    background: var(--bg-secondary);
    border-color: var(--border-color);
}

.card-header {
    background: var(--bg-primary);
    border-bottom: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .settings-section {
        padding: 16px;
    }
    
    .color-preview {
        flex-direction: column;
    }
}
</style>

<script>
// 색상 피커 동기화
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = colorInput.nextElementSibling;
    
    colorInput.addEventListener('input', function() {
        textInput.value = this.value;
    });
});

// 초대 코드 필요 토글
const requireInviteCode = document.getElementById('requireInviteCode');
const inviteCodeInput = document.querySelector('input[name="invite_code"]');

if (requireInviteCode && inviteCodeInput) {
    function toggleInviteCode() {
        inviteCodeInput.required = requireInviteCode.checked;
        inviteCodeInput.closest('.col-12').style.display = requireInviteCode.checked ? 'block' : 'none';
    }
    
    requireInviteCode.addEventListener('change', toggleInviteCode);
    toggleInviteCode();
}

// 메인 설정 폼 제출 확인
document.getElementById('mainSettingsForm').addEventListener('submit', function(e) {
    if (!confirm('설정을 저장하시겠습니까?')) {
        e.preventDefault();
    }
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>