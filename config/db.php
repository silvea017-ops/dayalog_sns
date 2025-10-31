<?php
// config/db.php - PDO connection
$DB_HOST = '127.0.0.1';
$DB_NAME = 'dayalog';
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP 기본 비밀번호 없는 경우
$OPTIONS = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $OPTIONS);
} catch (PDOException $e) {
    // 개발 환경에서는 에러 메시지를 보여주되, 실제 서비스에선 숨겨야 함
    exit('Database connection failed: ' . $e->getMessage());
}
?>
