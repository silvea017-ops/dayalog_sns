<?php
// public/pages/login.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

session_start();

// 이미 로그인된 경우 리다이렉트
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        // password 컬럼 추가!
        $stmt = $pdo->prepare("
            SELECT user_id, username, nickname, email, password, profile_img, bio, 
                   is_private, is_admin, show_all_tab, created_at 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // show_all_tab 기본값 설정 (NULL이면 1로)
            if (!isset($user['show_all_tab']) || $user['show_all_tab'] === null) {
                $user['show_all_tab'] = 1;
                
                // DB 업데이트
                $stmt = $pdo->prepare("UPDATE users SET show_all_tab = 1 WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
            }
            
            $_SESSION['user'] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'],
                'email' => $user['email'],
                'profile_img' => $user['profile_img'],
                'bio' => $user['bio'],
                'is_private' => $user['is_private'],
                'is_admin' => $user['is_admin'],
                'show_all_tab' => (int)$user['show_all_tab'],
                'created_at' => $user['created_at']
            ];
            
            header('Location: ' . BASE_URL . '/pages/index.php');
            exit;
        } else {
            $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
        }
    } else {
        $error = '모든 필드를 입력해주세요.';
    }
}

require_once INCLUDES_PATH . '/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="auth-card">
                <div class="text-center mb-4">
                    <img src="<?php echo ASSETS_URL; ?>/images/logo.svg" alt="Dayalog" style="width: 64px; height: 64px;">
                    <h2 class="mt-3">로그인</h2>
                    <p class="text-muted">Dayalog에 오신 것을 환영합니다</p>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">아이디 또는 이메일</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">비밀번호</label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password" id="password" class="form-control" required>
                            <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">로그인</button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">계정이 없으신가요? <a href="<?php echo BASE_URL; ?>/pages/register.php">회원가입</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.auth-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 40px;
    box-shadow: var(--shadow-md);
}

.auth-card .form-control {
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.auth-card .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.auth-card .btn-primary {
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
}

/* 비밀번호 입력 래퍼 */
.password-input-wrapper {
    position: relative;
}

.password-input-wrapper .form-control {
    padding-right: 48px;
}

.password-toggle-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
}

.password-toggle-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.password-toggle-btn:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
}
</style>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.querySelector('.eye-icon');
    const eyeOffIcon = document.querySelector('.eye-off-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.style.display = 'none';
        eyeOffIcon.style.display = 'block';
    } else {
        passwordInput.type = 'password';
        eyeIcon.style.display = 'block';
        eyeOffIcon.style.display = 'none';
    }
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>