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
$field_errors = []; // 필드별 에러 저장
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // === 1. 기본 필수 입력 검증 ===
    if (empty($username)) {
        $errors[] = '아이디를 입력해주세요.';
        $field_errors['username'] = '아이디를 입력해주세요.';
    }
    if (empty($email)) {
        $errors[] = '이메일을 입력해주세요.';
        $field_errors['email'] = '이메일을 입력해주세요.';
    }
    if (empty($nickname)) {
        $errors[] = '닉네임을 입력해주세요.';
        $field_errors['nickname'] = '닉네임을 입력해주세요.';
    }
    if (empty($password)) {
        $errors[] = '비밀번호를 입력해주세요.';
        $field_errors['password'] = '비밀번호를 입력해주세요.';
    }
    if (empty($password_confirm)) {
        $errors[] = '비밀번호 확인을 입력해주세요.';
        $field_errors['password_confirm'] = '비밀번호 확인을 입력해주세요.';
    }
    
    // === 2. 아이디 검증 ===
    if (!empty($username)) {
        // 길이 검증
        if (strlen($username) < 3) {
            $errors[] = '아이디는 최소 3자 이상이어야 합니다.';
            $field_errors['username'] = '아이디는 최소 3자 이상이어야 합니다.';
        } elseif (strlen($username) > 20) {
            $errors[] = '아이디는 최대 20자까지 가능합니다.';
            $field_errors['username'] = '아이디는 최대 20자까지 가능합니다.';
        }
        
        // 형식 검증: 영문 소문자로 시작, 영문 소문자/숫자/언더스코어만 허용
        elseif (!preg_match('/^[a-z][a-z0-9_]{2,19}$/', $username)) {
            $errors[] = '아이디는 영문 소문자로 시작하고, 영문 소문자/숫자/언더스코어(_)만 사용 가능합니다.';
            $field_errors['username'] = '아이디는 영문 소문자로 시작하고, 영문 소문자/숫자/언더스코어(_)만 사용 가능합니다.';
        }
        
        // 연속된 언더스코어 금지
        elseif (strpos($username, '__') !== false) {
            $errors[] = '언더스코어는 연속으로 사용할 수 없습니다.';
            $field_errors['username'] = '언더스코어는 연속으로 사용할 수 없습니다.';
        }
        
        // 시작과 끝에 언더스코어 금지
        elseif (preg_match('/^_|_$/', $username)) {
            $errors[] = '아이디는 언더스코어로 시작하거나 끝날 수 없습니다.';
            $field_errors['username'] = '아이디는 언더스코어로 시작하거나 끝날 수 없습니다.';
        }
        
        // 예약어 체크
        else {
            $reserved_words = ['admin', 'root', 'system', 'moderator', 'dayalog', 'test', 'api', 'public', 'user', 'null', 'undefined'];
            if (in_array(strtolower($username), $reserved_words)) {
                $errors[] = '사용할 수 없는 아이디입니다.';
                $field_errors['username'] = '사용할 수 없는 아이디입니다.';
            }
        }
    }
    
    // === 3. 이메일 검증 ===
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '올바른 이메일 형식이 아닙니다.';
            $field_errors['email'] = '올바른 이메일 형식이 아닙니다.';
        } elseif (strlen($email) > 100) {
            $errors[] = '이메일이 너무 깁니다.';
            $field_errors['email'] = '이메일이 너무 깁니다.';
        }
    }
    
    // === 4. 닉네임 검증 ===
    if (!empty($nickname)) {
        if (strlen($nickname) < 1) {
            $errors[] = '닉네임을 입력해주세요.';
            $field_errors['nickname'] = '닉네임을 입력해주세요.';
        } elseif (strlen($nickname) > 20) {
            $errors[] = '닉네임은 최대 20자까지 가능합니다.';
            $field_errors['nickname'] = '닉네임은 최대 20자까지 가능합니다.';
        }
        
        // 특수문자 제한 (한글, 영문, 숫자, 일부 특수문자만 허용)
        elseif (!preg_match('/^[가-힣a-zA-Z0-9\s._-]+$/u', $nickname)) {
            $errors[] = '닉네임에는 한글, 영문, 숫자, 공백, 마침표(.), 언더스코어(_), 하이픈(-)만 사용 가능합니다.';
            $field_errors['nickname'] = '닉네임에는 한글, 영문, 숫자, 공백, 마침표(.), 언더스코어(_), 하이픈(-)만 사용 가능합니다.';
        }
        
        // 공백으로만 구성된 닉네임 방지
        elseif (trim($nickname) === '') {
            $errors[] = '유효한 닉네임을 입력해주세요.';
            $field_errors['nickname'] = '유효한 닉네임을 입력해주세요.';
        }
    }
    
    // === 5. 비밀번호 검증 ===
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = '비밀번호는 최소 6자 이상이어야 합니다.';
            $field_errors['password'] = '비밀번호는 최소 6자 이상이어야 합니다.';
        } elseif (strlen($password) > 100) {
            $errors[] = '비밀번호가 너무 깁니다.';
            $field_errors['password'] = '비밀번호가 너무 깁니다.';
        }
        
        // 비밀번호 강도 체크 (영문+숫자 조합 권장)
        elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = '비밀번호는 영문과 숫자를 모두 포함해야 합니다.';
            $field_errors['password'] = '비밀번호는 영문과 숫자를 모두 포함해야 합니다.';
        }
    }
    
    // 비밀번호 확인 일치 여부
    if (!empty($password) && !empty($password_confirm)) {
        if ($password !== $password_confirm) {
            $errors[] = '비밀번호가 일치하지 않습니다.';
            $field_errors['password_confirm'] = '비밀번호가 일치하지 않습니다.';
        }
    }
    
    // === 6. 중복 체크 (서버 측 재확인) ===
    if (empty($errors)) {
        // 아이디 중복 체크
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = '이미 사용 중인 아이디입니다.';
            $field_errors['username'] = '이미 사용 중인 아이디입니다.';
        }
        
        // 이메일 중복 체크
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = '이미 사용 중인 이메일입니다.';
            $field_errors['email'] = '이미 사용 중인 이메일입니다.';
        }
        
        // 닉네임 중복 체크 (선택사항)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE nickname = ?");
        $stmt->execute([$nickname]);
        if ($stmt->fetch()) {
            $errors[] = '이미 사용 중인 닉네임입니다.';
            $field_errors['nickname'] = '이미 사용 중인 닉네임입니다.';
        }
    }
    
    // === 7. 회원가입 처리 ===
    if (empty($errors)) {
        try {
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
            }
        } catch (PDOException $e) {
            $errors[] = '회원가입 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
            error_log('Registration error: ' . $e->getMessage());
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
                
                <form method="POST" id="registerForm">
                    <!-- 아이디 -->
                    <div class="mb-3">
                        <label class="form-label">아이디 <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="username" 
                               class="form-control <?php echo isset($field_errors['username']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               required autofocus>
                        <small class="form-text text-muted">영문 소문자로 시작, 영문 소문자/숫자/언더스코어(_)만 사용 (3-20자)</small>
                        <?php if (isset($field_errors['username'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($field_errors['username']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="validation-feedback" id="usernameFeedback"></div>
                    </div>
                    
                    <!-- 이메일 -->
                    <div class="mb-3">
                        <label class="form-label">이메일 <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" 
                               class="form-control <?php echo isset($field_errors['email']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required>
                        <small class="form-text text-muted">로그인 및 계정 복구에 사용됩니다</small>
                        <?php if (isset($field_errors['email'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($field_errors['email']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="validation-feedback" id="emailFeedback"></div>
                    </div>
                    
                    <!-- 닉네임 -->
                    <div class="mb-3">
                        <label class="form-label">닉네임 <span class="text-danger">*</span></label>
                        <input type="text" name="nickname" id="nickname" 
                               class="form-control <?php echo isset($field_errors['nickname']) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($_POST['nickname'] ?? ''); ?>" 
                               required>
                        <small class="form-text text-muted">게시글에 표시되는 이름 (1-20자)</small>
                        <?php if (isset($field_errors['nickname'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($field_errors['nickname']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 비밀번호 -->
                    <div class="mb-3">
                        <label class="form-label">비밀번호 <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" 
                               class="form-control <?php echo isset($field_errors['password']) ? 'is-invalid' : ''; ?>" 
                               required>
                        <small class="form-text text-muted">영문과 숫자를 포함한 6자 이상</small>
                        <?php if (isset($field_errors['password'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($field_errors['password']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 비밀번호 확인 -->
                    <div class="mb-3">
                        <label class="form-label">비밀번호 확인 <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirm" id="password_confirm" 
                               class="form-control <?php echo isset($field_errors['password_confirm']) ? 'is-invalid' : ''; ?>" 
                               required>
                        <?php if (isset($field_errors['password_confirm'])): ?>
                            <div class="invalid-feedback d-block">
                                <?php echo htmlspecialchars($field_errors['password_confirm']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <span class="btn-text">가입하기</span>
                            <span class="btn-spinner" style="display: none;">
                                <span class="spinner-border spinner-border-sm me-2"></span>처리 중...
                            </span>
                        </button>
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

.auth-card .form-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.auth-card .form-control {
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary);
    color: var(--text-primary);
    transition: all 0.2s;
}

.auth-card .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: var(--bg-primary);
}

.auth-card .form-control.is-valid {
    border-color: #28a745;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.auth-card .form-control.is-invalid {
    border-color: #dc3545;
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.validation-feedback {
    display: none;
    font-size: 0.875rem;
    margin-top: 0.5rem;
    padding: 8px 12px;
    border-radius: 6px;
}

.validation-feedback.valid {
    display: block;
    color: #28a745;
    background: rgba(40, 167, 69, 0.1);
}

.validation-feedback.invalid {
    display: block;
    color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
}

.validation-feedback.checking {
    display: block;
    color: var(--text-secondary);
    background: var(--bg-hover);
}

.auth-card .btn-primary {
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    position: relative;
}

.alert ul {
    padding-left: 20px;
    margin-bottom: 0;
}

.form-text {
    display: block;
    margin-top: 4px;
    font-size: 0.875rem;
}
</style>

<script>
// Debounce 함수
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// 아이디 실시간 검증 (선택적)
const usernameInput = document.getElementById('username');
const usernameFeedback = document.getElementById('usernameFeedback');

const checkUsername = debounce(function(value) {
    // 빈 값이거나 너무 짧으면 피드백 숨김
    if (!value || value.length < 3) {
        usernameFeedback.className = 'validation-feedback';
        usernameFeedback.textContent = '';
        usernameInput.classList.remove('is-valid', 'is-invalid');
        return;
    }
    
    // 형식 검증
    if (!/^[a-z][a-z0-9_]{2,19}$/.test(value)) {
        return; // 형식이 맞지 않으면 중복 체크 안 함
    }
    
    if (value.includes('__')) {
        return;
    }
    
    if (/^_|_$/.test(value)) {
        return;
    }
    
    // 서버에 중복 체크
    usernameFeedback.className = 'validation-feedback checking';
    usernameFeedback.textContent = '확인 중...';
    
    fetch('<?php echo BASE_URL; ?>/api/check_duplicate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `type=username&value=${encodeURIComponent(value)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.available) {
            usernameFeedback.className = 'validation-feedback valid';
            usernameFeedback.textContent = data.message;
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
        } else {
            usernameFeedback.className = 'validation-feedback invalid';
            usernameFeedback.textContent = data.message;
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.add('is-invalid');
        }
    })
    .catch(err => {
        console.error('Username check error:', err);
        usernameFeedback.className = 'validation-feedback';
        usernameFeedback.textContent = '';
        usernameInput.classList.remove('is-valid', 'is-invalid');
    });
}, 600);

usernameInput.addEventListener('input', function(e) {
    checkUsername(e.target.value.trim());
});

// 이메일 실시간 검증 (선택적)
const emailInput = document.getElementById('email');
const emailFeedback = document.getElementById('emailFeedback');

const checkEmail = debounce(function(value) {
    if (!value) {
        emailFeedback.className = 'validation-feedback';
        emailFeedback.textContent = '';
        emailInput.classList.remove('is-valid', 'is-invalid');
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
        return; // 형식이 맞지 않으면 중복 체크 안 함
    }
    
    // 서버에 중복 체크
    emailFeedback.className = 'validation-feedback checking';
    emailFeedback.textContent = '확인 중...';
    
    fetch('<?php echo BASE_URL; ?>/api/check_duplicate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `type=email&value=${encodeURIComponent(value)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.available) {
            emailFeedback.className = 'validation-feedback valid';
            emailFeedback.textContent = data.message;
            emailInput.classList.remove('is-invalid');
            emailInput.classList.add('is-valid');
        } else {
            emailFeedback.className = 'validation-feedback invalid';
            emailFeedback.textContent = data.message;
            emailInput.classList.remove('is-valid');
            emailInput.classList.add('is-invalid');
        }
    })
    .catch(err => {
        console.error('Email check error:', err);
        emailFeedback.className = 'validation-feedback';
        emailFeedback.textContent = '';
        emailInput.classList.remove('is-valid', 'is-invalid');
    });
}, 600);

emailInput.addEventListener('input', function(e) {
    checkEmail(e.target.value.trim());
});

// 폼 제출 시 로딩 표시
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnSpinner = submitBtn.querySelector('.btn-spinner');
    
    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnSpinner.style.display = 'inline';
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>