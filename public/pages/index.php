<?php
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($request == '/' || $request == '/index.php') {
    include 'main.php';
} else {
    $path = trim($request, '/');
    $file_path = __DIR__ . '/' . $path;
    
    if (file_exists($file_path) && is_file($file_path)) {
        if (pathinfo($file_path, PATHINFO_EXTENSION) == 'php') {
            include $file_path;
        } else {
            readfile($file_path);
        }
    } else {
        include 'main.php';
    }
}
?>
<script>
// 간단하고 확실한 URL 고정
(function() {
    'use strict';
    
    function fixURL() {
        if (window.location.pathname !== '/' || window.location.search !== '') {
            history.replaceState(null, '', '/');
        }
    }
    
    // 즉시 실행
    fixURL();
    
    // 여러 시점에서 실행
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixURL);
    }
    window.addEventListener('load', fixURL);
    window.addEventListener('pageshow', fixURL);
    window.addEventListener('popstate', fixURL);
    
    // 초기 5초간만 주기적 체크
    let count = 0;
    const timer = setInterval(function() {
        fixURL();
        count++;
        if (count > 50) clearInterval(timer);
    }, 100);
})();
</script>