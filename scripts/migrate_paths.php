<?php
/**
 * 경로 일괄 수정 스크립트
 * 실행: php scripts/migrate_paths.php
 */

$rootDir = dirname(__DIR__);
$publicDir = $rootDir . '/public';

// 수정할 경로 매핑
$replacements = [
    // 이미지 경로
    "'../assets/" => "ASSETS_URL . '/",
    '"../assets/' => 'ASSETS_URL . "/',
    
    // 업로드 경로
    "'../uploads/" => "UPLOADS_URL . '/",
    '"../uploads/' => 'UPLOADS_URL . "/',
    
    // 페이지 링크
    "href=\"index.php\"" => "href=\"<?php echo BASE_URL; ?>/pages/index.php\"",
    "href='index.php'" => "href='<?php echo BASE_URL; ?>/pages/index.php'",
    
    // 프로필 이미지 (특별 처리)
    '$user[\'profile_img\'] ? \'../\'.htmlspecialchars($user[\'profile_img\'])' => 'getProfileImageUrl($user[\'profile_img\'])',
    '$_SESSION[\'user\'][\'profile_img\'] ? \'../\'.htmlspecialchars($_SESSION[\'user\'][\'profile_img\'])' => 'getProfileImageUrl($_SESSION[\'user\'][\'profile_img\'])',
];

// 추가로 require_once 경로 수정
$requireReplacements = [
    "require_once __DIR__ . '/../config/db.php';" => "require_once __DIR__ . '/../../config/db.php';",
    "require_once __DIR__ . '/../includes/auth.php';" => "require_once __DIR__ . '/../../includes/auth.php';",
    "require_once __DIR__ . '/../includes/header.php';" => "require_once dirname(__DIR__, 2) . '/includes/header.php';",
];

function processFile($filePath, $replacements) {
    if (!file_exists($filePath)) {
        echo "❌ 파일 없음: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $originalContent) {
        // 백업 생성
        $backupPath = $filePath . '.backup';
        file_put_contents($backupPath, $originalContent);
        
        // 수정된 내용 저장
        file_put_contents($filePath, $content);
        echo "✅ 수정됨: $filePath\n";
        echo "   백업: $backupPath\n";
        return true;
    }
    
    return false;
}

function scanDirectory($dir, $replacements, $extensions = ['php']) {
    $modifiedCount = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
            if (in_array($ext, $extensions)) {
                if (processFile($file->getPathname(), $replacements)) {
                    $modifiedCount++;
                }
            }
        }
    }
    
    return $modifiedCount;
}

echo "=== Dayalog 경로 마이그레이션 시작 ===\n\n";

// 1. Pages 폴더 처리
echo "📁 public/pages/ 처리 중...\n";
$count = scanDirectory($publicDir . '/pages', array_merge($replacements, $requireReplacements));
echo "수정된 파일: $count개\n\n";

// 2. API 폴더 처리
echo "📁 public/api/ 처리 중...\n";
$count = scanDirectory($publicDir . '/api', array_merge($replacements, $requireReplacements));
echo "수정된 파일: $count개\n\n";

echo "=== 마이그레이션 완료 ===\n";
echo "⚠️  백업 파일(.backup)을 확인한 후 문제없으면 삭제하세요.\n";
echo "💡 각 파일 상단에 다음 코드를 추가하세요:\n";
echo "   require_once __DIR__ . '/../../config/paths.php';\n";