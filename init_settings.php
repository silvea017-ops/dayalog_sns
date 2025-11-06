<?php
/**
 * 사이트 설정 초기화 스크립트
 * site_settings 테이블 생성 및 기본값 설정
 * 
 * 사용법: 브라우저에서 http://localhost/dayalog/init_settings.php 접속
 */

require_once __DIR__ . '/config/db.php';

$results = [];

try {
    // 1. site_settings 테이블 생성
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            setting_id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = ['status' => 'success', 'message' => 'site_settings 테이블 생성 완료'];

    // 2. users 테이블에 is_admin 컬럼 추가 (이미 있으면 무시)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER profile_img");
        $results[] = ['status' => 'success', 'message' => 'users 테이블에 is_admin 컬럼 추가 완료'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = ['status' => 'info', 'message' => 'is_admin 컬럼이 이미 존재합니다'];
        } else {
            throw $e;
        }
    }

    // 3. 기본 설정 값 삽입
    $default_settings = [
        ['favicon_path', 'assets/images/logo.svg'],
        ['site_name', 'Dayalog'],
        ['site_description', '일상을 공유하는 감성 SNS'],
        ['site_logo', 'assets/images/logo.svg']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO site_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($default_settings as list($key, $value)) {
        $stmt->execute([$key, $value]);
    }
    $results[] = ['status' => 'success', 'message' => '기본 설정 값 삽입 완료'];

    // 4. 기본 파비콘 폴더 생성
    $favicon_dir = __DIR__ . '/assets/images/favicons';
    if (!is_dir($favicon_dir)) {
        mkdir($favicon_dir, 0755, true);
        $results[] = ['status' => 'success', 'message' => 'favicons 폴더 생성 완료'];
    } else {
        $results[] = ['status' => 'info', 'message' => 'favicons 폴더가 이미 존재합니다'];
    }

    // 5. 현재 설정 확인
    $stmt = $pdo->query("SELECT * FROM site_settings ORDER BY setting_key");
    $current_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $success = true;

} catch (PDOException $e) {
    $results[] = ['status' => 'error', 'message' => '오류 발생: ' . $e->getMessage()];
    $success = false;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사이트 설정 초기화 - Dayalog</title>
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
        
        .card {
            max-width: 900px;
            margin: 0 auto;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 40, 100, 0.08);
        }
        
        .card-body {
            padding: 48px 40px;
        }
        
        .card-title {
            color: #111827;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 32px;
        }
        
        h5 {
            color: #111827;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .result-item {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            border: 1px solid;
        }
        
        .result-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }
        
        .result-info {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }
        
        .result-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        
        .settings-table {
            font-size: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .settings-table th {
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
            padding: 12px 16px;
        }
        
        .settings-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .settings-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .settings-table code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            color: #2563eb;
            font-size: 13px;
        }
        
        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            border-radius: 12px;
            padding: 20px;
        }
        
        .alert-info strong {
            color: #1e3a8a;
        }
        
        .alert-info ol {
            margin-top: 12px;
            padding-left: 20px;
        }
        
        .alert-info li {
            margin-bottom: 8px;
        }
        
        .alert-info code {
            background: white;
            padding: 2px 8px;
            border-radius: 4px;
            color: #2563eb;
            font-size: 13px;
            border: 1px solid #bfdbfe;
        }
        
        .alert-info a {
            color: #1e40af;
            font-weight: 600;
            text-decoration: none;
        }
        
        .alert-info a:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }
        
        .btn {
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
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
        
        .mb-4 {
            margin-bottom: 32px;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 32px 24px;
            }
            
            .settings-table {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title text-center">⚙️ 사이트 설정 초기화</h2>

                <div class="mb-4">
                    <h5>실행 결과</h5>
                    <?php foreach ($results as $result): ?>
                        <div class="result-item result-<?php echo $result['status']; ?>">
                            <?php
                            $icon = [
                                'success' => '✓',
                                'info' => 'ℹ',
                                'error' => '✗'
                            ][$result['status']];
                            echo $icon . ' ' . htmlspecialchars($result['message']);
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($success && !empty($current_settings)): ?>
                <div class="mb-4">
                    <h5>현재 설정 값</h5>
                    <div class="table-responsive">
                        <table class="table settings-table">
                            <thead>
                                <tr>
                                    <th>설정 키</th>
                                    <th>설정 값</th>
                                    <th>업데이트 시간</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_settings as $setting): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($setting['setting_key']); ?></code></td>
                                    <td><?php echo htmlspecialchars($setting['setting_value']); ?></td>
                                    <td><?php echo htmlspecialchars($setting['updated_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>다음 단계:</strong>
                    <ol class="mb-0 mt-2">
                        <li>관리자 계정이 필요하면 <a href="create_admin.php">create_admin.php</a>를 실행하세요</li>
                        <li>또는 기존 사용자를 관리자로 만들려면:
                            <br><code>UPDATE users SET is_admin = 1 WHERE username = '사용자명';</code>
                        </li>
                        <li>로그인 후 프로필 드롭다운 → "사이트 설정"에서 파비콘을 변경할 수 있습니다</li>
                        <li><strong>이 파일(init_settings.php)을 삭제하세요</strong></li>
                    </ol>
                </div>

                <div class="d-flex gap-2">
                    <a href="public/pages/index.php" class="btn btn-primary flex-fill">메인으로 이동</a>
                    <a href="public/pages/login.php" class="btn btn-outline-primary flex-fill">로그인</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>