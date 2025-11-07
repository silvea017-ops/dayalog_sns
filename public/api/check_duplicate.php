<?php
// public/api/check_duplicate.php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CONFIG_PATH . '/db.php';

$response = ['success' => false, 'available' => false, 'message' => ''];

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '잘못된 요청입니다.';
    echo json_encode($response);
    exit;
}

$type = $_POST['type'] ?? ''; // 'username' or 'email'
$value = trim($_POST['value'] ?? '');

// 입력값 검증
if (empty($type) || empty($value)) {
    $response['message'] = '필수 항목이 누락되었습니다.';
    echo json_encode($response);
    exit;
}

try {
    if ($type === 'username') {
        // 아이디 형식 검증
        if (strlen($value) < 3 || strlen($value) > 20) {
            $response['message'] = '아이디는 3-20자 사이여야 합니다.';
            echo json_encode($response);
            exit;
        }
        
        // 정규표현식: 영문 소문자로 시작, 영문 소문자/숫자/언더스코어만 허용
        if (!preg_match('/^[a-z][a-z0-9_]{2,19}$/', $value)) {
            $response['message'] = '아이디는 영문 소문자로 시작하고, 영문 소문자/숫자/언더스코어(_)만 사용 가능합니다.';
            echo json_encode($response);
            exit;
        }
        
        // 예약어 체크
        $reserved = ['admin', 'root', 'system', 'moderator', 'dayalog', 'test', 'api', 'public'];
        if (in_array(strtolower($value), $reserved)) {
            $response['message'] = '사용할 수 없는 아이디입니다.';
            echo json_encode($response);
            exit;
        }
        
        // 중복 체크
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$value]);
        
        if ($stmt->fetch()) {
            $response['message'] = '이미 사용 중인 아이디입니다.';
        } else {
            $response['success'] = true;
            $response['available'] = true;
            $response['message'] = '사용 가능한 아이디입니다.';
        }
        
    } elseif ($type === 'email') {
        // 이메일 형식 검증
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = '올바른 이메일 형식이 아닙니다.';
            echo json_encode($response);
            exit;
        }
        
        // 이메일 길이 체크
        if (strlen($value) > 100) {
            $response['message'] = '이메일이 너무 깁니다.';
            echo json_encode($response);
            exit;
        }
        
        // 중복 체크
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$value]);
        
        if ($stmt->fetch()) {
            $response['message'] = '이미 사용 중인 이메일입니다.';
        } else {
            $response['success'] = true;
            $response['available'] = true;
            $response['message'] = '사용 가능한 이메일입니다.';
        }
        
    } else {
        $response['message'] = '잘못된 타입입니다.';
    }
    
} catch (PDOException $e) {
    $response['message'] = '서버 오류가 발생했습니다.';
    error_log('Check duplicate error: ' . $e->getMessage());
}

echo json_encode($response);