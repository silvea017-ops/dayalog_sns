<?php
/**
 * Dayalog 설치 프로세스 실행
 * DB 테이블 생성, 관리자 계정 생성, 설정 파일 생성
 */

if (!isset($_SESSION['install'])) {
    header('Location: install.php?step=1');
    exit;
}

$install_data = $_SESSION['install'];
$errors = [];
$success_steps = [];

try {
    // 1. DB 연결
    $pdo = new PDO(
        "mysql:host={$install_data['db_host']};dbname={$install_data['db_name']};charset=utf8mb4",
        $install_data['db_user'],
        $install_data['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $success_steps[] = 'DB 연결 성공';
    
    // 2. 테이블 생성
    $schema_file = __DIR__ . '/database/schema.sql';
    
    // schema.sql 파일이 있으면 사용, 없으면 직접 SQL 실행
    if (file_exists($schema_file)) {
        $sql_file = file_get_contents($schema_file);
        if ($sql_file === false) {
            throw new Exception('schema.sql 파일을 읽을 수 없습니다.');
        }
        $pdo->exec($sql_file);
    } else {
        // schema.sql이 없을 경우 직접 테이블 생성
        $create_tables_sql = "
        CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            nickname VARCHAR(50) NOT NULL,
            email VARCHAR(100),
            profile_img VARCHAR(255),
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS posts (
            post_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            image_path VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS comments (
            comment_id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_post_id (post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS likes (
            like_id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (post_id, user_id),
            FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS follows (
            follow_id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL,
            following_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_follow (follower_id, following_id),
            FOREIGN KEY (follower_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_follower (follower_id),
            INDEX idx_following (following_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS site_settings (
            setting_id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // 세미콜론으로 분리하여 각 쿼리 실행
        $queries = array_filter(array_map('trim', explode(';', $create_tables_sql)));
        foreach ($queries as $query) {
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }
    }
    $success_steps[] = '데이터베이스 테이블 생성 완료';
    
    // 3. 관리자 계정 생성
    $admin = $install_data['admin'];
    $hashed_password = password_hash($admin['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, nickname, email, is_admin, created_at) 
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $admin['username'],
        $hashed_password,
        $admin['nickname'],
        $admin['email'] ?? null
    ]);
    $success_steps[] = '관리자 계정 생성 완료';
    
    // 4. 기본 설정 값 삽입
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
    $success_steps[] = '기본 설정 완료';
    
    // 5. db.php 설정 파일 생성
    $config_dir = __DIR__ . '/config';
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }
    
    $db_config = "<?php
/**
 * 데이터베이스 연결 설정
 * 자동 생성됨 - " . date('Y-m-d H:i:s') . "
 */

\$host = '{$install_data['db_host']}';
\$dbname = '{$install_data['db_name']}';
\$username = '{$install_data['db_user']}';
\$password = '" . addslashes($install_data['db_pass']) . "';

try {
    \$pdo = new PDO(
        \"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\",
        \$username,
        \$password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException \$e) {
    die('데이터베이스 연결 실패: ' . \$e->getMessage());
}
";
    
    file_put_contents($config_dir . '/db.php', $db_config);
    $success_steps[] = 'DB 설정 파일 생성 완료';
    
    // 6. 필요한 디렉토리 생성
    $directories = [
        __DIR__ . '/public/uploads',
        __DIR__ . '/public/uploads/profiles',
        __DIR__ . '/public/uploads/posts',
        __DIR__ . '/assets/images/favicons'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    $success_steps[] = '필요한 디렉토리 생성 완료';
    
    // 7. 설치 완료 표시 파일 생성
    $install_info = [
        'installed_at' => date('Y-m-d H:i:s'),
        'admin_username' => $admin['username'],
        'version' => '1.0.0'
    ];
    file_put_contents($config_dir . '/.installed', json_encode($install_info, JSON_PRETTY_PRINT));
    $success_steps[] = '설치 완료';
    
    $install_success = true;
    
} catch (PDOException $e) {
    $errors[] = 'DB 오류: ' . $e->getMessage();
    $install_success = false;
} catch (Exception $e) {
    $errors[] = '설치 오류: ' . $e->getMessage();
    $install_success = false;
}
?>

<style>
    .installation-content h3 {
        color: #111827;
        font-weight: 600;
        font-size: 1.5rem;
        margin-bottom: 28px;
    }
    
    .installation-section {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 28px;
        margin-bottom: 24px;
    }
    
    .installation-section h5 {
        color: #111827;
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 20px;
    }
    
    .progress-item {
        display: flex;
        align-items: center;
        padding: 14px 16px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .progress-icon {
        width: 24px;
        height: 24px;
        margin-right: 12px;
        font-size: 18px;
        font-weight: bold;
    }
    
    .success-icon {
        color: #10b981;
    }
    
    .error-icon {
        color: #ef4444;
    }
    
    .success-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
    }
    
    .success-box h4 {
        color: #15803d;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 20px;
    }
    
    .success-box p {
        color: #166534;
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .success-box strong {
        color: #14532d;
        font-weight: 600;
    }
    
    .warning-box {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        color: #92400e;
    }
    
    .warning-box strong {
        display: block;
        margin-bottom: 12px;
        color: #78350f;
        font-weight: 600;
    }
    
    .warning-box ul {
        margin: 12px 0 0 0;
        padding-left: 20px;
    }
    
    .warning-box li {
        margin-bottom: 8px;
        font-size: 14px;
    }
    
    .warning-box code {
        background: white;
        padding: 2px 8px;
        border-radius: 4px;
        color: #2563eb;
        font-size: 13px;
        border: 1px solid #fde68a;
    }
    
    .error-box {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        color: #991b1b;
    }
    
    .action-buttons {
        display: flex;
        gap: 12px;
    }
    
    .action-buttons .btn {
        flex: 1;
    }
</style>

<div class="installation-content">
    <h3>설치 진행 중...</h3>

    <div class="installation-section">
        <h5>설치 단계</h5>
        <?php foreach ($success_steps as $step): ?>
            <div class="progress-item">
                <div class="progress-icon success-icon">✓</div>
                <div><?php echo htmlspecialchars($step); ?></div>
            </div>
        <?php endforeach; ?>
        
        <?php foreach ($errors as $error): ?>
            <div class="progress-item">
                <div class="progress-icon error-icon">✗</div>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($install_success): ?>
        <div class="success-box">
            <h4>🎉 Dayalog 설치가 완료되었습니다!</h4>
            <p><strong>관리자 계정:</strong> <?php echo htmlspecialchars($admin['username']); ?></p>
            <p><strong>닉네임:</strong> <?php echo htmlspecialchars($admin['nickname']); ?></p>
        </div>
        
        <div class="warning-box">
            <strong>⚠️ 보안을 위해:</strong>
            <ul class="mb-0">
                <li><code>install.php</code> 파일을 삭제하세요</li>
                <li><code>install_process.php</code> 파일을 삭제하세요</li>
                <li>관리자 계정 정보를 안전하게 보관하세요</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="public/pages/index.php" class="btn btn-install">메인으로 이동</a>
            <a href="public/pages/login.php" class="btn btn-outline-primary">로그인</a>
        </div>
        
        <?php
        // 세션 정리
        unset($_SESSION['install']);
        ?>
    <?php else: ?>
        <div class="error-box">
            ❌ 설치 중 오류가 발생했습니다. 위의 오류 메시지를 확인하고 다시 시도해주세요.
        </div>
        
        <div class="text-end">
            <a href="install.php?step=1" class="btn btn-outline-danger">처음부터 다시 시도</a>
        </div>
    <?php endif; ?>
</div>