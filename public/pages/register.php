<?php
// public/pages/register.php
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

session_start();

// 이미 로그인된 경우 리다이렉트
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // 유효성 검사
    if (empty($username)) $errors[] = '아이디를 입력해주세요.';
    if (empty($email)) $errors[] = '이메일을 입력해주세요.';
    if (empty($nickname)) $errors[] = '닉네임을 입력해주세요.';
    if (empty($password)) $errors[] = '비밀번호를 입력해주세요.';
    if ($password !== $password_confirm) $errors[] = '비밀번호가 일치하지 않습니다.';
    if (strlen($password) < 6) $errors[] = '비밀번호는 최소 6자 이상이어야 합니다.';
    
    // 중복 체크
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) $errors[] = '이미 사용 중인 아이디입니다.';
        
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = '이미 사용 중인 이메일입니다.';
    }
    
    // 회원가입 처리
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, nickname, password) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $email, $nickname, $hashed_password])) {
            // 자동 로그인
            $user_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $_SESSION['user'] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'],
                'email' => $user['email'],
                'profile_img' => $user['profile_img'],
                'bio' => $user['bio'],
                'created_at' => $user['created_at']
            ];
            
            $_SESSION['success_message'] = '회원가입을 환영합니다!';
            header('Location: ' . BASE_URL . '/pages/welcome.php');
            exit;
        } else {
            $errors[] = '회원가입 중 오류가 발생했습니다.';
        }
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
                    <h2 class="mt-3">회원가입</h2>
                    <p class="text-muted">Dayalog와 함께 시작하세요</p>
                </div>
                
                <?php if(!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">아이디</label>
                        <input type="text" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus>
                        <small class="text-muted">영문, 숫자, 언더스코어(_)만 사용 가능</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">이메일</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">닉네임</label>
                        <input type="text" name="nickname" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['nickname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">비밀번호</label>
                        <input type="password" name="password" class="form-control" required>
                        <small class="text-muted">최소 6자 이상</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">비밀번호 확인</label>
                        <input type="password" name="password_confirm" class="form-control" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">가입하기</button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-0">이미 계정이 있으신가요? <a href="<?php echo BASE_URL; ?>/pages/login.php">로그인</a></p>
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

.alert ul {
    padding-left: 20px;
}
</style>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>