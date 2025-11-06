// public/assets/js/media-viewer.js
let mediaViewerSwiper = null;
let currentPostMedia = [];

// 미디어 모달 열기
function openMediaModal(postId, startIndex = 0) {
  // BASE_URL 가져오기 (HTML에서 전역 변수로 설정되어 있어야 함)
  const baseUrl = window.BASE_URL || "";

  // 해당 게시물의 미디어 정보 가져오기
  fetch(`${baseUrl}/api/get_post_media.php?post_id=${postId}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success && data.media.length > 0) {
        currentPostMedia = data.media;
        initMediaViewer(startIndex);
      } else {
        console.error("미디어를 불러올 수 없습니다.");
      }
    })
    .catch((err) => {
      console.error("Error loading media:", err);
      alert("미디어를 불러오는데 실패했습니다.");
    });
}

// 미디어 뷰어 초기화
function initMediaViewer(startIndex) {
  const baseUrl = window.BASE_URL || "";
  const wrapper = document.getElementById("mediaViewerWrapper");

  if (!wrapper) {
    console.error("mediaViewerWrapper not found");
    return;
  }

  wrapper.innerHTML = "";

  currentPostMedia.forEach((media) => {
    const slide = document.createElement("div");
    slide.className = "swiper-slide";

    if (media.media_type === "video") {
      slide.innerHTML = `
        <video controls>
          <source src="${baseUrl}/${media.image_path}" type="video/mp4">
          <source src="${baseUrl}/${media.image_path}" type="video/webm">
          브라우저가 비디오를 지원하지 않습니다.
        </video>
      `;
    } else {
      slide.innerHTML = `<img src="${baseUrl}/${media.image_path}" alt="media">`;
    }

    wrapper.appendChild(slide);
  });

  document.getElementById("totalMediaCount").textContent =
    currentPostMedia.length;
  document.getElementById("currentMediaIndex").textContent = startIndex + 1;

  const modal = document.getElementById("mediaViewerModal");
  modal.classList.add("active");
  document.body.style.overflow = "hidden";

  // 기존 스와이퍼 제거
  if (mediaViewerSwiper) {
    mediaViewerSwiper.destroy(true, true);
  }

  // Swiper가 로드되었는지 확인
  if (typeof Swiper === "undefined") {
    console.error("Swiper library not loaded");
    alert("이미지 뷰어를 로드할 수 없습니다.");
    closeMediaViewer();
    return;
  }

  // 새 스와이퍼 생성
  setTimeout(() => {
    mediaViewerSwiper = new Swiper("#mediaViewerSwiper", {
      initialSlide: startIndex,
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      keyboard: {
        enabled: true,
        onlyInViewport: false,
      },
      loop: currentPostMedia.length > 1,
      on: {
        slideChange: function () {
          document.getElementById("currentMediaIndex").textContent =
            this.realIndex + 1;

          // 비디오 자동재생 처리
          const slides = this.slides;
          const currentSlide = slides[this.activeIndex];
          const video = currentSlide
            ? currentSlide.querySelector("video")
            : null;

          // 모든 비디오 일시정지
          document.querySelectorAll("#mediaViewerSwiper video").forEach((v) => {
            v.pause();
            v.currentTime = 0;
          });

          // 현재 슬라이드 비디오만 재생
          if (video) {
            video
              .play()
              .catch((err) => console.log("Auto-play prevented:", err));
          }
        },
      },
    });
  }, 100);
}

// 미디어 뷰어 닫기
function closeMediaViewer() {
  const modal = document.getElementById("mediaViewerModal");
  if (!modal) return;

  modal.classList.remove("active");
  document.body.style.overflow = "";

  // 모든 비디오 정지
  document.querySelectorAll("#mediaViewerSwiper video").forEach((video) => {
    video.pause();
    video.currentTime = 0;
  });

  if (mediaViewerSwiper) {
    mediaViewerSwiper.destroy(true, true);
    mediaViewerSwiper = null;
  }

  currentPostMedia = [];
}

// ESC 키로 닫기
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    closeMediaViewer();
  }
});

// 모달 배경 클릭 시 닫기
document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("mediaViewerModal");
  if (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === this) {
        closeMediaViewer();
      }
    });
  }
});
