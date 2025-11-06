<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>환영합니다 - Dayalog</title>

  <!-- ✅ Lottie 라이브러리 추가 -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.0/lottie.min.js"></script>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: white;
    }
    
    .welcome-container {
      text-align: center;
      color: black;
      padding: 40px;
      max-width: 600px;
      position: relative;
      z-index: 10;
    }
    
    .lottie-animation {
      width: 300px;
      height: 300px;
      margin: 0 auto 30px;
      opacity: 0;
      animation: fadeInScale 0.8s ease-out 0.3s forwards;
    }
    
    .welcome-text {
      opacity: 0;
      animation: slideUp 0.8s ease-out 0.6s forwards;
    }
    
    .welcome-text h1 {
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 20px;
      line-height: 1.3;
      color: black;
    }
    
    .welcome-text p {
      font-size: 20px;
      font-weight: 400;
      opacity: 0.9;
      margin-bottom: 40px;
      line-height: 1.6;
      color: black;;
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes fadeInScale {
      from {
        opacity: 0;
        transform: scale(0.8);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    @media (max-width: 768px) {
      .lottie-animation {
        width: 250px;
        height: 250px;
      }
      
      .welcome-text h1 {
        font-size: 32px;
      }
      
      .welcome-text p {
        font-size: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="welcome-container">
    <div id="welcomeAnimation" class="lottie-animation"></div>
    
    <div class="welcome-text">
      <h1>Dayalog에 오신 것을<br>환영합니다!</h1>
      <p>일상에 특별함을 더해볼까요?<br>당신의 이야기를 시작하세요.</p>
    </div>
  </div>

  <script>
    // ✅ Lottie 애니메이션 초기화
    const animation = lottie.loadAnimation({
      container: document.getElementById('welcomeAnimation'),
      renderer: 'svg',
      loop: true,
      autoplay: true,
      path: '../assets/animation/Welcome.json'
    });
    
    // ✅ 3초 후 자동 리다이렉트
    setTimeout(() => {
      window.location.href = 'index.php';
    }, 4000);
  </script>
</body>
</html>
