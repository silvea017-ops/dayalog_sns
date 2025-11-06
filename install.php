<?php
/**
 * Dayalog 설치 마법사
 * 그누보드 스타일의 단계별 설치 프로세스
 */

session_start();

// 이미 설치되었는지 확인
$install_lock = __DIR__ . '/config/.installed';
if (file_exists($install_lock)) {
    die('
    <!DOCTYPE html>
    <html lang="ko">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dayalog 설치완료</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                background: #f8f9fc;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                padding: 20px;
            }
            .container {
                width: 100%;
                max-width: 500px;
                margin: 0 auto;
            }
            .card {
                width: 100%;
                background: white;
                border: none;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0, 40, 100, 0.08);
            }
            .card-body {
                padding: 3rem;
                text-align: center;
            }
            .icon {
                width: 64px;
                height: 64px;
                background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
                color: white;
                margin: 0 auto 1.5rem;
            }
            h2 {
                color: #111827;
                font-weight: 700;
                margin-bottom: 1rem;
            }
            .text-muted {
                color: #6b7280;
                margin-bottom: 1.5rem;
            }
            .btn-primary {
                background: #2563eb;
                border: none;
                color: white;
                padding: 12px 32px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 15px;
                text-decoration: none;
                display: inline-block;
                transition: all 0.2s;
            }
            .btn-primary:hover {
                background: #1e40af;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-body text-center">
                    <div class="icon">✓</div>
                    <h2 class="mb-3">설치완료</h2>
                    <p class="text-muted mb-4">Dayalog가 이미 설치되어 있습니다.</p>
                    <a href="public/pages/index.php" class="btn btn-primary">메인으로 이동</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    ');
}

// 현재 단계
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        $current_step = (int)$_POST['step'];
        
        switch ($current_step) {
            case 2: // 데이터베이스 설정 저장
                $_SESSION['install'] = [
                    'db_host' => $_POST['db_host'],
                    'db_name' => $_POST['db_name'],
                    'db_user' => $_POST['db_user'],
                    'db_pass' => $_POST['db_pass'],
                    'db_charset' => 'utf8mb4'
                ];
                
                // DB 연결 테스트
                try {
                    $test_pdo = new PDO(
                        "mysql:host={$_SESSION['install']['db_host']};charset=utf8mb4",
                        $_SESSION['install']['db_user'],
                        $_SESSION['install']['db_pass'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    
                    // 데이터베이스가 없으면 생성
                    $test_pdo->exec("CREATE DATABASE IF NOT EXISTS `{$_SESSION['install']['db_name']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    header('Location: install.php?step=3');
                    exit;
                } catch (PDOException $e) {
                    $error = 'DB 연결 실패: ' . $e->getMessage();
                }
                break;
                
            case 3: // 관리자 계정 정보 저장
                $_SESSION['install']['admin'] = [
                    'username' => $_POST['admin_username'],
                    'password' => $_POST['admin_password'],
                    'nickname' => $_POST['admin_nickname'],
                    'email' => $_POST['admin_email']
                ];
                
                header('Location: install.php?step=4');
                exit;
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dayalog 설치</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8f9fc;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .install-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .install-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 40, 100, 0.08);
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 48px 40px;
            text-align: center;
        }
        
        .install-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        
        .install-header p {
            margin: 12px 0 0 0;
            opacity: 0.95;
            font-size: 1.05rem;
            font-weight: 400;
        }
        
        .install-body {
            padding: 48px 40px;
        }
        
        /* 단계 표시 */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 48px;
            position: relative;
            padding: 0 20px;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: #e5e7eb;
            z-index: 0;
        }
        
        .step-item {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .step-item.active .step-circle {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .step-item.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .step-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }
        
        .step-item.active .step-label {
            color: #2563eb;
            font-weight: 600;
        }
        
        .step-item.completed .step-label {
            color: #10b981;
        }
        
        /* 폼 섹션 */
        .form-section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 24px;
        }
        
        .form-section h5 {
            margin-bottom: 24px;
            color: #111827;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .form-label {
            color: #374151;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .text-muted {
            color: #6b7280 !important;
            font-size: 13px;
        }
        
        /* 버튼 */
        .btn {
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .btn-install {
            background: #2563eb;
            border: none;
            color: white;
        }
        
        .btn-install:hover {
            background: #1e40af;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-outline-secondary {
            border: 1px solid #d1d5db;
            color: #6b7280;
            background: white;
        }
        
        .btn-outline-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #374151;
        }
        
        .btn-outline-primary {
            border: 2px solid #2563eb;
            color: #2563eb;
            background: white;
        }
        
        .btn-outline-primary:hover {
            background: #eff6ff;
            border-color: #1e40af;
            color: #1e40af;
        }
        
        .btn-outline-danger {
            border: 2px solid #ef4444;
            color: #ef4444;
            background: white;
        }
        
        .btn-outline-danger:hover {
            background: #fef2f2;
            border-color: #dc2626;
            color: #dc2626;
        }
        
        /* 요구사항 체크 */
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .requirement-icon {
            width: 24px;
            height: 24px;
            margin-right: 12px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .check-ok {
            color: #10b981;
        }
        
        .check-fail {
            color: #ef4444;
        }
        
        /* 알림 */
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        
        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        /* 하단 텍스트 */
        .footer-text {
            text-align: center;
            margin-top: 32px;
            color: #6b7280;
            font-size: 14px;
        }
        
        h3 {
            color: #111827;
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 28px;
        }
        
        @media (max-width: 768px) {
            .install-body {
                padding: 32px 24px;
            }
            
            .install-header {
                padding: 36px 24px;
            }
            
            .step-indicator {
                padding: 0 10px;
            }
            
            .step-circle {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }
            
            .step-label {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1>Dayalog 설치</h1>
                <p>일상을 공유하는 감성 SNS</p>
            </div>
            
            <div class="install-body">
                <!-- 단계 표시 -->
                <div class="step-indicator">
                    <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                        <div class="step-circle">1</div>
                        <div class="step-label">환경 체크</div>
                    </div>
                    <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                        <div class="step-circle">2</div>
                        <div class="step-label">DB 설정</div>
                    </div>
                    <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                        <div class="step-circle">3</div>
                        <div class="step-label">관리자 생성</div>
                    </div>
                    <div class="step-item <?php echo $step >= 4 ? 'active' : ''; ?>">
                        <div class="step-circle">4</div>
                        <div class="step-label">설치 완료</div>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-custom mb-4">
                        <strong>오류:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Step 1: 환경 체크 -->
                <?php if ($step === 1): ?>
                    <h3>시스템 환경 체크</h3>
                    
                    <div class="form-section">
                        <h5>필수 요구사항</h5>
                        <?php
                        $requirements = [
                            'PHP 버전 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
                            'PDO 확장' => extension_loaded('pdo'),
                            'PDO MySQL 드라이버' => extension_loaded('pdo_mysql'),
                            'GD 라이브러리' => extension_loaded('gd'),
                            'JSON 확장' => extension_loaded('json'),
                            'config 폴더 쓰기 권한' => is_writable(__DIR__ . '/config'),
                            'uploads 폴더 쓰기 권한' => is_writable(__DIR__ . '/public/uploads') || mkdir(__DIR__ . '/public/uploads', 0755, true)
                        ];
                        
                        $all_passed = true;
                        foreach ($requirements as $name => $passed):
                            if (!$passed) $all_passed = false;
                        ?>
                            <div class="requirement-item">
                                <div class="requirement-icon <?php echo $passed ? 'check-ok' : 'check-fail'; ?>">
                                    <?php echo $passed ? '✓' : '✗'; ?>
                                </div>
                                <div><?php echo $name; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($all_passed): ?>
                        <div class="alert alert-success alert-custom">
                            ✓ 모든 시스템 요구사항을 만족합니다!
                        </div>
                        <div class="text-end">
                            <a href="install.php?step=2" class="btn btn-install">다음 단계 →</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger alert-custom">
                            일부 요구사항을 만족하지 못했습니다. 서버 설정을 확인해주세요.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Step 2: DB 설정 -->
                <?php if ($step === 2): ?>
                    <h3>데이터베이스 설정</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="2">
                        
                        <div class="form-section">
                            <h5>MySQL 데이터베이스 정보</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">DB 호스트</label>
                                <input type="text" name="db_host" class="form-control" value="localhost" required>
                                <small class="text-muted">일반적으로 localhost 또는 127.0.0.1</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">DB 이름</label>
                                <input type="text" name="db_name" class="form-control" placeholder="dayalog" required>
                                <small class="text-muted">데이터베이스가 없으면 자동으로 생성됩니다</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">DB 사용자명</label>
                                <input type="text" name="db_user" class="form-control" value="root" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">DB 비밀번호</label>
                                <input type="password" name="db_pass" class="form-control">
                                <small class="text-muted">비밀번호가 없으면 비워두세요</small>
                            </div>
                        </div>

                        <div class="alert alert-info alert-custom">
                             <strong>팁:</strong> 대부분의 호스팅 업체는 제어판(cPanel)에서 데이터베이스 정보를 확인할 수 있습니다.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="install.php?step=1" class="btn btn-outline-secondary">← 이전</a>
                            <button type="submit" class="btn btn-install">연결 테스트 및 다음 →</button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Step 3: 관리자 계정 -->
                <?php if ($step === 3): ?>
                    <h3>관리자 계정 생성</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="3">
                        
                        <div class="form-section">
                            <h5>관리자 정보</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">사용자명 (아이디) *</label>
                                <input type="text" name="admin_username" class="form-control" 
                                       pattern="[a-zA-Z0-9_]{4,20}" required
                                       placeholder="admin">
                                <small class="text-muted">영문, 숫자, 밑줄만 사용 가능 (4-20자)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">비밀번호 *</label>
                                <input type="password" name="admin_password" class="form-control" 
                                       minlength="6" required>
                                <small class="text-muted">최소 6자 이상</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">닉네임 *</label>
                                <input type="text" name="admin_nickname" class="form-control" required
                                       placeholder="관리자">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">이메일</label>
                                <input type="email" name="admin_email" class="form-control"
                                       placeholder="admin@example.com">
                                <small class="text-muted">선택사항</small>
                            </div>
                        </div>

                        <div class="alert alert-warning alert-custom">
                            <strong>중요:</strong> 관리자 계정 정보는 안전하게 보관하세요. 사이트 설정 및 관리 권한을 가집니다.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="install.php?step=2" class="btn btn-outline-secondary">← 이전</a>
                            <button type="submit" class="btn btn-install">설치 시작 →</button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Step 4: 설치 진행 -->
                <?php if ($step === 4): 
                    require_once __DIR__ . '/install_process.php';
                ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer-text">
            <small>© 2025 Dayalog. All rights reserved.</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>