<?php
// public/pages/logout.php
require_once dirname(__DIR__, 2) . '/config/paths.php';

session_start();
session_destroy();

header('Location: ' . BASE_URL . '/pages/login.php');
exit;
?>