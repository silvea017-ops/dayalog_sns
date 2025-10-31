# Dayalog - Mini SNS Template (PHP + MySQL)

## 개요
Dayalog은 PHP + MySQL 기반의 간단한 미니 SNS 템플릿입니다.
이 패키지는 XAMPP 환경의 `htdocs/dayalog`에 그대로 복사하여 실행할 수 있도록 구성되어 있습니다.

## 설치 가이드 (XAMPP 기준)
1. 압축을 풀고 `dayalog` 폴더를 XAMPP의 `htdocs`에 복사하세요.
2. `config/db.php`의 DB 정보(host, dbname, user, pass)를 본인의 MySQL 설정으로 수정하세요.
3. `schema.sql`을 MySQL에 적용하여 테이블을 생성하세요. (phpMyAdmin에서 import 가능)
4. `uploads/` 폴더에 웹서버가 쓰기 권한을 가지도록 설정하세요.
5. 브라우저에서 `http://localhost/dayalog/public/`로 접속하세요.

## 포함 기능
- 회원가입 / 로그인 / 로그아웃 (password_hash / password_verify 사용)
- 프로필 수정 (프로필 사진 업로드)
- 게시글 작성 (텍스트 + 이미지 업로드)
- 전체 피드 보기 (최신순)
- 다크/라이트 테마 토글 (사용자 선택 저장)

## 주의
이 템플릿은 학습/과제용으로 제공됩니다. 배포용으로 사용하기 전에는 추가적인 보안 검토가 필요합니다.
