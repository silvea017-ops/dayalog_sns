<?php
// toggle theme cookie and redirect back
if (session_status() === PHP_SESSION_NONE) session_start();
$current = isset($_COOKIE['dayalog_theme']) ? $_COOKIE['dayalog_theme'] : 'light';
$next = $current === 'dark' ? 'light' : 'dark';
setcookie('dayalog_theme', $next, time()+60*60*24*30, '/');
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $referer);
exit;
