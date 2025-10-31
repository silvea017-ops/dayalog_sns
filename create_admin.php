<?php
/**
 * 관리자 계정 생성 스크립트
 * 이 파일은 한 번만 실행하고 삭제하세요!
 * 
 * 사용법: 브라우저에서 http://localhost/dayalog/create_admin.php 접속
 */

require_once __DIR__ . '/config/db.php';

// 보안을 위해 이미 관리자가 있으면 실행 차단
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
$admin_count = $stmt->fetch()['count'];

if ($admin_count > 0) {
    die('
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>관리자 계정 생성</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .alert { padding: 15px; border-radius: 5px; margin: 20px 0; }
            .alert-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
            .alert-info { background: #d1ecf1; border: 1px solid #17a2b8; color: #0c5460; }
        </style>
    </head>
    <body>
        <h1>⚠️ 관리자 계정 생성</h1>
        <div class="alert alert-warning">
            <strong>이미 관리자 계정이 존재합니다!</strong><br>
            보안을 위해 추가 생성이 차단되었습니다.
        </div>
        <div class="alert alert-info">
            <strong>기존 사용자를 관리자로 만들려면:</strong><br>
            phpMyAdmin에서 다음 SQL을 실행하세요:<br>
            <code>UPDATE users SET is_admin = 1 WHERE username = \'사용자명\';</code>
        </div>
        <p><strong>⚠️ 중요: 이 파일(create_admin.php)을 즉시 삭제하세요!</strong></p>
    </body>
    </html>
    ');
}

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nickname = trim($_POST['nickname']);
    $email = trim($_POST['email']);
    
    // 유효성 검사
    if (empty($username) || empty($password) || empty($nickname)) {
        $error = '모든 필수 항목을 입력해주세요.';
    } elseif (strlen($password) < 6) {
        $error = '비밀번호는 최소 6자 이상이어야 합니다.';
    } else {
        // 중복 체크
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = '이미 존재하는 사용자명입니다.';
        } else {
            // 관리자 계정 생성
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, nickname, email, is_admin, created_at) 
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$username, $hashed_password, $nickname, $email]);
                
                $success = true;
            } catch (PDOException $e) {
                $error = '계정 생성 중 오류가 발생했습니다: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 계정 생성 - Dayalog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">🔐 관리자 계정 생성</h2>
                
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success">
                        <h4>✅ 관리자 계정이 생성되었습니다!</h4>
                        <p class="mb-2"><strong>사용자명:</strong> <?php echo htmlspecialchars($username); ?></p>
                        <p class="mb-3"><strong>닉네임:</strong> <?php echo htmlspecialchars($nickname); ?></p>
                        <a href="public/login.php" class="btn btn-primary w-100 mb-2">로그인하기</a>
                    </div>
                    <div class="alert alert-danger">
                        <strong>⚠️ 보안 경고:</strong><br>
                        즉시 이 파일(<code>create_admin.php</code>)을 서버에서 삭제하세요!
                    </div>
                <?php else: ?>
                    <div class="warning-box">
                        <strong>⚠️ 주의사항:</strong>
                        <ul class="mb-0 mt-2">
                            <li>이 페이지는 최초 1회만 사용하세요</li>
                            <li>관리자 계정 생성 후 <strong>즉시 이 파일을 삭제</strong>하세요</li>
                            <li>생성된 계정 정보를 안전하게 보관하세요</li>
                        </ul>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">사용자명 (아이디) *</label>
                            <input type="text" name="username" class="form-control" required 
                                   value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                                   pattern="[a-zA-Z0-9_]{4,20}" 
                                   title="영문, 숫자, 밑줄만 사용 가능 (4-20자)">
                            <small class="text-muted">영문, 숫자, 밑줄만 사용 가능 (4-20자)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">비밀번호 *</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">최소 6자 이상</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">닉네임 *</label>
                            <input type="text" name="nickname" class="form-control" required
                                   value="<?php echo isset($nickname) ? htmlspecialchars($nickname) : ''; ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">이메일 (선택)</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">관리자 계정 생성</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>