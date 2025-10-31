<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 로그인하지 않은 사용자는 로그인 페이지로
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>환영합니다 - Dayalog</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: linear-gradient(135deg, #4c8bb4ff 0%, #2f9bb6ff 50%, #83dce2ff 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    
    .welcome-container {
      text-align: center;
      color: white;
      padding: 40px;
      max-width: 600px;
      position: relative;
      z-index: 10;
    }
    
    .logo {
      width: 100px;
      height: 100px;
      margin: 0 auto 30px;
      opacity: 0;
      animation: fadeInScale 0.8s ease-out 0.3s forwards;
      filter: drop-shadow(0 8px 16px rgba(56, 48, 163, 0.2));
    }
    
    .welcome-text {
      opacity: 0;
      animation: slideUp 0.8s ease-out forwards;
    }
    
    .welcome-text h1 {
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 20px;
      animation-delay: 0.6s;
      line-height: 1.3;
    }
    
    .welcome-text p {
      font-size: 20px;
      font-weight: 400;
      opacity: 0.9;
      margin-bottom: 40px;
      animation-delay: 0.9s;
      line-height: 1.6;
    }
    
    .auto-redirect-notice {
      font-size: 14px;
      opacity: 0.8;
      margin-top: 20px;
      animation: fadeIn 0.8s ease-out 1.5s forwards;
      opacity: 0;
    }
    
    /* Fireworks */
    .fireworks-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
    }
    
    .firework-burst {
      position: absolute;
      pointer-events: none;
    }
    
    .firework-particle {
      display: inline-block;
      width: 10px;
      height: 10px;
      border: 2px dotted rgb(255, 99, 71);
      border-radius: 50%;
      opacity: 0;
      position: absolute;
    }
    
    @keyframes burst {
      0% {
        transform: scale(0);
        opacity: 1;
      }
      60%, 90% {
        transform: scale(1);
      }
      100% {
        transform: scale(1.2);
        opacity: 0;
      }
    }
    
    .firework-particle.active {
      animation: burst 2s forwards;
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
    
    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 0.8;
      }
    }
  </style>
</head>
<body>
  <div class="fireworks-container" id="fireworksContainer"></div>
  
  <div class="welcome-container">
    <img src="../assets/images/logo.svg" alt="Dayalog" class="logo">
    
    <div class="welcome-text">
      <h1>Dayalog에 오신 것을<br>환영합니다!</h1>
      <p>일상에 특별함을 더해볼까요?<br>당신의 이야기를 시작하세요.</p>
    </div>
  </div>
  
  <script>
    const fireworksContainer = document.getElementById('fireworksContainer');
    
    // 폭죽 색상 배열
    const colors = [
      'rgb(255, 99, 71)',
      'rgb(50, 205, 50)',
      'rgb(135, 206, 235)',
      'rgba(150, 231, 255, 1)',
      'rgba(255, 255, 255, 1)', 
      'rgba(255, 239, 247, 1)',  
      'rgb(255, 165, 0)',
      'rgb(147, 112, 219)'
    ];
    
    // 폭죽 생성 함수
    function createFirework() {
      const burst = document.createElement('div');
      burst.className = 'firework-burst';
      
      const logo = document.querySelector('.logo');
      const logoRect = logo.getBoundingClientRect();
      const centerX = logoRect.left + logoRect.width / 2;
      const centerY = logoRect.top + logoRect.height / 2;
      
      const angle = Math.random() * Math.PI * 2;
      const distance = Math.random() * 200 + 50;
      const x = centerX + Math.cos(angle) * distance;
      const y = centerY + Math.sin(angle) * distance;
      
      burst.style.left = x + 'px';
      burst.style.top = y + 'px';
      
      const particleCount = Math.floor(Math.random() * 5) + 6;
      
      for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('span');
        particle.className = 'firework-particle';
        
        const color = colors[Math.floor(Math.random() * colors.length)];
        particle.style.borderColor = color;
        
        const size = Math.floor(Math.random() * 16) + 20;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.borderWidth = Math.floor(size / 5) + 'px';
        
        const particleAngle = (360 / particleCount) * i + Math.random() * 40;
        const particleDistance = Math.random() * 80 + 40;
        const radians = particleAngle * Math.PI / 180;
        const offsetX = Math.cos(radians) * particleDistance;
        const offsetY = Math.sin(radians) * particleDistance;
        
        particle.style.left = offsetX + 'px';
        particle.style.top = offsetY + 'px';
        particle.style.animationDelay = (Math.random() * 0.4) + 's';
        
        burst.appendChild(particle);
      }
      
      fireworksContainer.appendChild(burst);
      
      setTimeout(() => {
        const particles = burst.querySelectorAll('.firework-particle');
        particles.forEach(p => p.classList.add('active'));
      }, 10);
      
      setTimeout(() => {
        burst.remove();
      }, 3000);
    }
    
    // 초기 폭죽 연속 발사
    setTimeout(() => createFirework(), 800);
    setTimeout(() => createFirework(), 1200);
    setTimeout(() => createFirework(), 1600);
    setTimeout(() => createFirework(), 2000);
    setTimeout(() => createFirework(), 2400);
    
    // 이후 랜덤하게 폭죽 발사
    const fireworkInterval = setInterval(() => {
      if (Math.random() > 0.5) {
        createFirework();
      }
    }, 1500);
    
    // 3초 후 자동 리다이렉트
    setTimeout(function() {
      clearInterval(fireworkInterval);
      window.location.href = 'index.php';
    }, 3000);
  </script>
</body>
</html>