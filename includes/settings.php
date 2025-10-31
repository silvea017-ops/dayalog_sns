<?php
// includes/settings.php - 사이트 설정 관리 함수

/**
 * 설정 값 가져오기
 */
function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * 설정 값 저장/업데이트
 */
function updateSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 모든 설정 가져오기
 */
function getAllSettings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * 파비콘 경로 가져오기 (캐싱 지원)
 */
function getFaviconPath($pdo) {
    static $favicon = null;
    if ($favicon === null) {
        $favicon = getSetting($pdo, 'favicon_path', 'puble/assets/images/favicon.ico');
    }
    return $favicon;
}