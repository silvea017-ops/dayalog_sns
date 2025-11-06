<?php
// config/paths.php
// 프로젝트 루트 경로 설정

// 절대 경로
define('ROOT_PATH', dirname(__DIR__)); 
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('FUNCTIONS_PATH', ROOT_PATH . '/functions');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
define('ASSETS_PATH', PUBLIC_PATH . '/assets');

// 웹 경로 (브라우저에서 접근)
define('BASE_URL', '/public');
define('ASSETS_URL', '/public/assets');
define('UPLOADS_URL', '/public/uploads');

// 페이지 경로
define('PAGES_PATH', PUBLIC_PATH . '/pages');
define('API_PATH', PUBLIC_PATH . '/api');

/**
 * 절대 경로를 웹 경로로 변환
 */
function toWebPath($absolutePath) {
    if (strpos($absolutePath, PUBLIC_PATH) === 0) {
        return str_replace(PUBLIC_PATH, BASE_URL, $absolutePath);
    }
    return $absolutePath;
}

/**
 * 업로드된 파일의 웹 경로 반환 (수정됨)
 */
function getUploadUrl($filename) {
    if (empty($filename)) return ASSETS_URL . '/images/sample.png';
    
    // 이미 http/https로 시작하는 전체 URL
    if (strpos($filename, 'http') === 0) {
        return $filename;
    }
    
    // 이미 /public/으로 시작하는 경우
    if (strpos($filename, '/public/') === 0) {
        return $filename;
    }
    
    // public/으로 시작하는 경우
    if (strpos($filename, 'public/') === 0) {
        return '/' . $filename;
    }
    
    // uploads/로 시작하는 경우 (DB에 이 형태로 저장됨)
    if (strpos($filename, 'uploads/') === 0) {
        return '/public/' . $filename;
    }
    
    // assets/로 시작하는 경우
    if (strpos($filename, 'assets/') === 0) {
        return '/public/' . $filename;
    }
    
    // 그 외의 경우 (파일명만 있는 경우)
    return UPLOADS_URL . '/' . basename($filename);
}

/**
 * 프로필 이미지 URL 반환 (수정됨)
 */
function getProfileImageUrl($profileImg) {
    if (empty($profileImg) || $profileImg === 'assets/images/sample.png') {
        return ASSETS_URL . '/images/sample.png';
    }
    
    // 이미 http/https로 시작하는 경우
    if (strpos($profileImg, 'http') === 0) {
        return $profileImg;
    }
    
    // '/public/'으로 시작하는 경우
    if (strpos($profileImg, '/public/') === 0) {
        return $profileImg;
    }
    
    // 'public/'으로 시작하는 경우
    if (strpos($profileImg, 'public/') === 0) {
        return '/' . $profileImg;
    }
    
    // 'uploads/'로 시작하는 경우
    if (strpos($profileImg, 'uploads/') === 0) {
        return '/public/' . $profileImg;
    }
    
    // 'assets/'로 시작하는 경우
    if (strpos($profileImg, 'assets/') === 0) {
        return '/public/' . $profileImg;
    }
    
    // 그 외 (파일명만 있는 경우)
    return UPLOADS_URL . '/profile/' . basename($profileImg);
}
?>