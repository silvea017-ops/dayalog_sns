</main>
</div> <!-- Close page-wrapper -->

<!-- 미디어 뷰어 모달 -->
<div class="media-viewer-modal" id="mediaViewerModal">
  <div class="media-viewer-layout">
    <!-- 왼쪽: 미디어 영역 -->
    <div class="media-viewer-main">
      <div class="media-viewer-header">
        <div class="media-counter">
          <span id="currentMediaIndex">1</span> / <span id="totalMediaCount">1</span>
        </div>
        <button class="media-viewer-close" onclick="closeMediaViewer()">×</button>
      </div>
      
      <div class="media-viewer-swiper">
        <div class="swiper-container" id="mediaViewerSwiper">
          <div class="swiper-wrapper" id="mediaViewerWrapper">
            <!-- 동적으로 추가됨 -->
          </div>
          <div class="swiper-button-prev"></div>
          <div class="swiper-button-next"></div>
        </div>
      </div>
    </div>
    
    <!-- 우측: 게시글 정보 -->
    <div class="media-viewer-sidebar">
      <div class="sidebar-header">
        <img src="" id="sidebarProfileImg" class="sidebar-profile-img" alt="profile">
        <div class="sidebar-user-info">
          <strong id="sidebarNickname"></strong>
          <small id="sidebarUsername"></small>
        </div>
      </div>
      
      <div class="sidebar-content">
        <p id="sidebarPostContent"></p>
        <small class="text-muted" id="sidebarPostTime"></small>
      </div>
      
      <div class="sidebar-stats">
        <div class="stat-item">
          <strong id="sidebarLikeCount">0</strong>
          <span>좋아요</span>
        </div>
        <div class="stat-item">
          <strong id="sidebarCommentCount">0</strong>
          <span>댓글</span>
        </div>
      </div>
      
      <div class="sidebar-actions">
        <button class="sidebar-action-btn" id="sidebarLikeBtn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
          </svg>
        </button>
        <a href="#" class="sidebar-action-btn" id="sidebarCommentBtn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
        </a>
        <button class="sidebar-action-btn" id="sidebarShareBtn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="18" cy="5" r="3"></circle>
            <circle cx="6" cy="12" r="3"></circle>
            <circle cx="18" cy="19" r="3"></circle>
            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
          </svg>
        </button>
      </div>
    </div>
  </div>
</div>

<footer class="footer py-4 border-top">
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        <p class="mb-0">&copy; 2025 Dayalog. All rights reserved.</p>
      </div>
      <div class="col-md-6 text-md-end">
        <a href="#" class="text-decoration-none me-3">개인정보처리방침</a>
        <a href="#" class="text-decoration-none me-3">이용약관</a>
        <a href="#" class="text-decoration-none">문의하기</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Swiper 라이브러리 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js"></script>

<!-- BASE_URL 전역 변수 설정 -->
<script>
window.BASE_URL = '<?php echo BASE_URL; ?>';
</script>

<!-- 미디어 뷰어 스크립트 -->
<script>
let mediaViewerSwiper = null;
let currentPostMedia = [];

// 미디어 모달 열기
function openMediaModal(postId, startIndex = 0) {
  const baseUrl = window.BASE_URL || '';
  
  console.log('=== Opening Media Modal ===');
  console.log('Post ID:', postId);
  console.log('Start Index:', startIndex);
  console.log('Base URL:', baseUrl);
  
  // 모달 먼저 표시
  const modal = document.getElementById('mediaViewerModal');
  if (!modal) {
    console.error('Modal not found!');
    return;
  }
  
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
  
  // 로딩 표시
  const wrapper = document.getElementById('mediaViewerWrapper');
  if (wrapper) {
    wrapper.innerHTML = '<div class="swiper-slide" style="display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">로딩중...</div>';
  }
  
  // 미디어 정보 가져오기
  const mediaUrl = `${baseUrl}/api/get_post_media.php?post_id=${postId}`;
  console.log('Fetching media from:', mediaUrl);
  
  fetch(mediaUrl)
    .then(res => {
      console.log('Media response status:', res.status);
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res.text();
    })
    .then(text => {
      console.log('Media response text:', text.substring(0, 500));
      const mediaData = JSON.parse(text);
      console.log('Media data parsed:', mediaData);
      
      if (!mediaData.success) {
        throw new Error(mediaData.message || '미디어를 불러올 수 없습니다');
      }
      
      if (!mediaData.media || mediaData.media.length === 0) {
        throw new Error('미디어가 없습니다');
      }
      
      currentPostMedia = mediaData.media;
      console.log('Media loaded:', currentPostMedia);
      
      // 미디어 뷰어 초기화
      initMediaViewer(startIndex);
      
      // 게시물 정보 가져오기
      const postUrl = `${baseUrl}/api/get_post_info.php?post_id=${postId}`;
      console.log('Fetching post info from:', postUrl);
      
      return fetch(postUrl);
    })
    .then(res => {
      console.log('Post info response status:', res.status);
      if (!res.ok) {
        console.warn('Post info fetch failed, but continuing...');
        return null;
      }
      return res.text();
    })
    .then(text => {
      if (!text) return;
      
      console.log('Post info response text:', text.substring(0, 500));
      const postData = JSON.parse(text);
      console.log('Post data parsed:', postData);
      
      if (postData.success && postData.post) {
        updateSidebar(postData.post, postId);
      }
    })
    .catch(err => {
      console.error('=== Error in openMediaModal ===');
      console.error('Error:', err);
      console.error('Error stack:', err.stack);
      
      // 에러 발생 시 모달 닫기
      closeMediaViewer();
      alert('미디어를 불러오는데 실패했습니다.\n' + err.message);
    });
}

// 사이드바 정보 업데이트
function updateSidebar(post, postId) {
  console.log('=== Updating Sidebar ===');
  console.log('Post:', post);
  
  const baseUrl = window.BASE_URL || '';
  
  try {
    document.getElementById('sidebarProfileImg').src = post.profile_img ? 
      `${baseUrl}/${post.profile_img}` : `${baseUrl}/assets/images/sample.png`;
    document.getElementById('sidebarNickname').textContent = post.nickname || '';
    document.getElementById('sidebarUsername').textContent = '@' + (post.username || '');
    document.getElementById('sidebarPostContent').textContent = post.content || '';
    document.getElementById('sidebarPostTime').textContent = post.created_at || '';
    document.getElementById('sidebarLikeCount').textContent = post.like_count || 0;
    document.getElementById('sidebarCommentCount').textContent = post.comment_count || 0;
    
    // 좋아요 버튼 상태
    const likeBtn = document.getElementById('sidebarLikeBtn');
    if (post.user_liked) {
      likeBtn.classList.add('liked');
      likeBtn.querySelector('svg').setAttribute('fill', 'currentColor');
    } else {
      likeBtn.classList.remove('liked');
      likeBtn.querySelector('svg').setAttribute('fill', 'none');
    }
    
    // 좋아요 버튼 클릭 이벤트
    likeBtn.onclick = function(e) {
      e.stopPropagation();
      if (typeof toggleLike === 'function') {
        toggleLike(postId, this);
      }
    };
    
    // 댓글 버튼 링크
    document.getElementById('sidebarCommentBtn').href = `${baseUrl}/pages/post_detail.php?id=${postId}`;
    
    // 공유 버튼
    document.getElementById('sidebarShareBtn').onclick = function(e) {
      e.stopPropagation();
      if (typeof sharePost === 'function') {
        sharePost(postId);
      }
    };
    
    console.log('Sidebar updated successfully');
  } catch (error) {
    console.error('Error updating sidebar:', error);
  }
}

// 미디어 뷰어 초기화
function initMediaViewer(startIndex) {
  console.log('=== Initializing Media Viewer ===');
  console.log('Start index:', startIndex);
  console.log('Media count:', currentPostMedia.length);
  
  const baseUrl = window.BASE_URL || '';
  const wrapper = document.getElementById('mediaViewerWrapper');
  
  if (!wrapper) {
    console.error('Wrapper not found!');
    return;
  }
  
  if (typeof Swiper === 'undefined') {
    console.error('Swiper library not loaded!');
    alert('Swiper 라이브러리를 불러올 수 없습니다.');
    return;
  }
  
  // 기존 Swiper 제거
  if (mediaViewerSwiper) {
    console.log('Destroying existing swiper');
    mediaViewerSwiper.destroy(true, true);
    mediaViewerSwiper = null;
  }
  
  // 슬라이드 생성
  wrapper.innerHTML = '';
  
  currentPostMedia.forEach((media, index) => {
    console.log(`Creating slide ${index}:`, media);
    
    const slide = document.createElement('div');
    slide.className = 'swiper-slide';
    
    if (media.media_type === 'video') {
      slide.innerHTML = `
        <video controls style="max-width: 90%; max-height: 90vh; object-fit: contain;">
          <source src="${baseUrl}/${media.image_path}" type="video/mp4">
          <source src="${baseUrl}/${media.image_path}" type="video/webm">
          브라우저가 비디오를 지원하지 않습니다.
        </video>
      `;
    } else {
      slide.innerHTML = `<img src="${baseUrl}/${media.image_path}" alt="media" style="max-width: 90%; max-height: 90vh; object-fit: contain;">`;
    }
    
    wrapper.appendChild(slide);
  });
  
  console.log('Slides created:', wrapper.children.length);
  
  // 카운터 업데이트
  document.getElementById('totalMediaCount').textContent = currentPostMedia.length;
  document.getElementById('currentMediaIndex').textContent = startIndex + 1;
  
  // Swiper 초기화
  setTimeout(() => {
    try {
      console.log('Creating Swiper instance...');
      
      mediaViewerSwiper = new Swiper('#mediaViewerSwiper', {
        initialSlide: startIndex,
        slidesPerView: 1,
        spaceBetween: 0,
        centeredSlides: true,
        navigation: {
          nextEl: '.swiper-button-next',
          prevEl: '.swiper-button-prev',
        },
        keyboard: {
          enabled: true,
        },
        loop: currentPostMedia.length > 1,
        on: {
          init: function() {
            console.log('Swiper initialized');
          },
          slideChange: function() {
            const realIndex = this.realIndex;
            console.log('Slide changed to:', realIndex);
            document.getElementById('currentMediaIndex').textContent = realIndex + 1;
            
            // 모든 비디오 정지
            document.querySelectorAll('#mediaViewerSwiper video').forEach(v => {
              v.pause();
              v.currentTime = 0;
            });
            
            // 현재 슬라이드 비디오 재생
            const currentSlide = this.slides[this.activeIndex];
            const video = currentSlide ? currentSlide.querySelector('video') : null;
            
            if (video) {
              video.play().catch(err => console.log('Auto-play prevented:', err));
            }
          }
        }
      });
      
      console.log('Swiper created successfully:', mediaViewerSwiper);
      
    } catch (error) {
      console.error('=== Swiper Creation Error ===');
      console.error('Error:', error);
      console.error('Stack:', error.stack);
      alert('Swiper 초기화 실패: ' + error.message);
    }
  }, 200);
}

// 미디어 뷰어 닫기
function closeMediaViewer() {
  console.log('=== Closing Media Viewer ===');
  
  const modal = document.getElementById('mediaViewerModal');
  if (!modal) return;
  
  modal.classList.remove('active');
  document.body.style.overflow = '';
  
  // 모든 비디오 정지
  document.querySelectorAll('#mediaViewerSwiper video').forEach(video => {
    video.pause();
    video.currentTime = 0;
  });
  
  // Swiper 제거
  if (mediaViewerSwiper) {
    mediaViewerSwiper.destroy(true, true);
    mediaViewerSwiper = null;
  }
  
  currentPostMedia = [];
  console.log('Modal closed');
}

// ESC 키로 닫기
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeMediaViewer();
  }
});

// 모달 배경 클릭 시 닫기
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('mediaViewerModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeMediaViewer();
      }
    });
  }
});
</script>

<style>
/* Sticky Footer 스타일 */
html, body {
  height: 100%;
  margin: 0;
}

.page-wrapper {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

main.container {
  flex: 1 0 auto;
}

.footer {
  flex-shrink: 0;
  background: var(--bg-primary);
  border-top: 1px solid var(--border-color);
  margin-top: auto;
}

.footer p,
.footer a {
  color: var(--text-secondary);
  font-size: 14px;
}

.footer a:hover {
  color: var(--primary-color);
}

/* 미디어 뷰어 모달 스타일 */
/* 미디어 뷰어 모달 스타일 */
.media-viewer-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.95);
  z-index: 10000;
}

.media-viewer-modal.active {
  display: block !important;
}

.media-viewer-layout {
  display: flex;
  width: 100%;
  height: 100vh;
  position: relative;
}

/* 왼쪽 미디어 영역 */
.media-viewer-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #000;
  position: relative;
  min-width: 0;
}

.media-viewer-header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  z-index: 100;
  background: linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);
  pointer-events: none;
}

.media-viewer-header > * {
  pointer-events: auto;
}

.media-counter {
  color: white;
  font-size: 15px;
  font-weight: 600;
  padding: 8px 16px;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 20px;
  backdrop-filter: blur(10px);
}

.media-viewer-close {
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: white;
  font-size: 28px;
  cursor: pointer;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s;
  backdrop-filter: blur(10px);
}

.media-viewer-close:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: scale(1.1);
}

.media-viewer-swiper {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100%;
  height: 100%;
}

.media-viewer-swiper .swiper-container {
  width: 100%;
  height: 100%;
  position: relative;
}

.media-viewer-swiper .swiper-wrapper {
  width: 100%;
  height: 100%;
}

.media-viewer-swiper .swiper-slide {
  display: flex !important;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: #000;
}

.media-viewer-swiper .swiper-slide img,
.media-viewer-swiper .swiper-slide video {
  max-width: 90%;
  max-height: 90vh;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
}

.media-viewer-swiper .swiper-button-prev,
.media-viewer-swiper .swiper-button-next {
  color: white;
  background: rgba(0, 0, 0, 0.5);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  backdrop-filter: blur(10px);
  z-index: 50;
}

.media-viewer-swiper .swiper-button-prev::after,
.media-viewer-swiper .swiper-button-next::after {
  font-size: 18px;
  font-weight: bold;
}

.media-viewer-swiper .swiper-button-prev:hover,
.media-viewer-swiper .swiper-button-next:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* 우측 사이드바 - 트위터 스타일 */
.media-viewer-sidebar {
  width: 350px;
  background: var(--bg-primary);
  display: flex;
  flex-direction: column;
  border-left: 1px solid var(--border-color);
  height: 100vh;
  overflow: hidden;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 16px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

.sidebar-profile-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.sidebar-user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.sidebar-user-info strong {
  color: var(--text-primary);
  font-size: 15px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-user-info small {
  color: var(--text-secondary);
  font-size: 15px;
  font-weight: 400;
}

.sidebar-content {
  padding: 16px;
  flex: 1;
  overflow-y: auto;
  border-bottom: 1px solid var(--border-color);
}

.sidebar-content p {
  color: var(--text-primary);
  font-size: 15px;
  line-height: 20px;
  margin: 0 0 12px 0;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.sidebar-content small {
  color: var(--text-secondary);
  font-size: 15px;
}

.sidebar-stats {
  padding: 12px 16px;
  display: flex;
  gap: 20px;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

.sidebar-stats .stat-item {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.sidebar-stats .stat-item strong {
  color: var(--text-primary);
  font-size: 15px;
  font-weight: 700;
}

.sidebar-stats .stat-item span {
  color: var(--text-secondary);
  font-size: 15px;
  font-weight: 400;
}

.sidebar-actions {
  padding: 12px 0;
  display: flex;
  justify-content: space-around;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

.sidebar-action-btn {
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 8px;
  border-radius: 50%;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.sidebar-action-btn:hover {
  background: var(--bg-hover);
}

.sidebar-action-btn:hover svg {
  color: var(--primary-color);
}

.sidebar-action-btn.liked {
  color: #f91880;
}

.sidebar-action-btn.liked svg {
  fill: #f91880;
  stroke: #f91880;
}

/* 모바일 반응형 */
@media (max-width: 1024px) {
  .media-viewer-sidebar {
    display: none;
  }
  
  .media-viewer-main {
    width: 100%;
  }
}
</style>

</body>
</html>