<?php
/**
 * 데이터베이스 연결 설정
 * 자동 생성됨 - 2025-10-25 22:38:18
 */

$host = 'localhost';
$dbname = 'dayalog';
$username = 'dayalog';
$password = 'popoto5568!';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('데이터베이스 연결 실패: ' . $e->getMessage());
}
