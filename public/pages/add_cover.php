<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['cover_img'])) {
    $_SESSION['error_message'] = '파일이 업로드되지 않았습니다.';
    header('Location: profile.php');
    exit;
}

$file = $_FILES['cover_img'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = '업로드 오류가 발생했습니다.';
    header('Location: profile.php');
    exit;
}

$uploaddir = __DIR__ . '/../uploads/';
if (!is_dir($uploaddir)) {
    @mkdir($uploaddir, 0755, true);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($ext, $allowed)) {
    $_SESSION['error_message'] = '지원하지 않는 파일 형식입니다.';
    header('Location: profile.php');
    exit;
}

$filename = 'cover_' . $_SESSION['user']['user_id'] . '_' . time() . '.' . $ext;
$dest = $uploaddir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $_SESSION['error_message'] = '파일 저장에 실패했습니다.';
    header('Location: profile.php');
    exit;
}

$image_path = 'uploads/' . $filename;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cover_images WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['user_id']]);
    $count = $stmt->fetch()['count'];
    $is_active = ($count === 0) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO cover_images (user_id, image_path, is_active, created_at) VALUES (?, ?, ?, NOW())");
    $result = $stmt->execute([$_SESSION['user']['user_id'], $image_path, $is_active]);

    if ($result) {
        $_SESSION['success_message'] = '헤더 이미지가 추가되었습니다.';
    } else {
        @unlink($dest);
        $_SESSION['error_message'] = 'DB 저장에 실패했습니다.';
    }
} catch (Exception $e) {
    @unlink($dest);
    $_SESSION['error_message'] = '오류: ' . $e->getMessage();
}

header('Location: profile.php');
exit;
?>