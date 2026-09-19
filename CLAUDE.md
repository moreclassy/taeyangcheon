# taeyangcheon

태양천(taeyang1000.com) 정적 웹사이트. HTML/CSS/JS + `php/` (메일 발송용).

## 서버 접근

- 호스팅: 카페24 웹호스팅 `10G 광아우토반 FullSSD Plus 절약형`, 서버 `uws8-wpm-025.cafe24.com` (112.175.85.160), PHP 8.4 / mariadb-10.x
- 2026-09-18에 PHP 7.3 → 8.4로 변경하면서 서버가 이전됨 (구 서버 `uws7-087`). 서버 이전 시 SSH 호스트키가 바뀌므로 `ssh-keygen -R taeyang1000.com` 후 재접속
- 제한된 셸이라 `whoami`, `wc` 등 일부 명령 없음
- 계정: `qudgk02`
- SSH 키: 레포 루트의 `qudgk02_key_20260918.pem` (`.gitignore`로 제외됨, 권한 600 유지)
- 카페24 SSH 키는 발급 후 30일 만료 (현재 키 만료일 2026-10-18). 만료되면 `나의 서비스 관리 > FTP/Shell 접속설정 > 인증키 재발급`(본인인증 필요)으로 새 pem을 받아 같은 파일명으로 교체
- SSH 30일 미접속 시 자동 차단됨
- `~/.ssh/config`에 `taeyang` 별칭이 등록되어 있음:

```
Host taeyang
    HostName taeyang1000.com
    User qudgk02
    IdentityFile /Users/byungha/repositories/taeyangcheon/qudgk02_key_20260918.pem
    IdentitiesOnly yes
```

접속:

```bash
ssh taeyang
# 별칭 없이 직접 접속할 때
ssh -i qudgk02_key_20260918.pem qudgk02@taeyang1000.com
```

## 배포

- 서버 웹 루트: `/home/hosting_users/qudgk02/www` (`~/www`)
- `~/www` 구조는 이 레포 루트와 동일함 (`index.html`, `css/`, `js/`, `php/` 등). 파일을 그대로 덮어쓰면 배포됨
- 파일 복사 예:

```bash
scp index.html taeyang:~/www/
scp -r css js images taeyang:~/www/
```

- `~/www/hosting_index.html`은 카페24 기본 파일이라 레포에 없음. 건드리지 않기
- `.pem` 파일은 절대 커밋하지 않기
- `backup/`은 서버 전체 백업 tarball 보관용 (`.gitignore`로 제외). 서버에는 레포에 없는 이미지가 있으므로 서버를 초기화하는 작업 전에는 항상 `ssh taeyang 'cd ~ && tar czf - www' > backup/www-backup-YYYYMMDD.tar.gz`로 받아둘 것
- macOS `tar`로 서버에 올리면 `._*` 메타파일이 함께 생기므로 `COPYFILE_DISABLE=1 tar ...`로 만들거나 업로드 후 `find ~/www -name '._*' -exec rm -f {} +`로 정리
- 공통 CSS/JS(`css/default-theme.css`, `js/theme-script.js`)를 고치면 `build.py`의 `VERSION`/`VERSION_OVERRIDE`를 올리고 다시 빌드해야 방문자 브라우저 캐시가 갱신됨

## 페이지 편집 / 빌드 (헤더·푸터 공통화)

- 루트의 `*.html` 6개는 **생성 파일**이다. 직접 고치지 말고 `src/`를 고친 뒤 `python3 build.py`를 실행해 다시 만든다 (PHP 없이 정적 파일로 배포하기 위해 빌드 방식 사용. 로컬·서버 모두 PHP CLI 없음)
  - `src/pages/<이름>.html`: 페이지 본문(`<!--header end-->`~`<!--footer start-->` 사이) + 맨 위 `<!--page ... -->` 메타 블록(title, description, canonical, og_image, preload, nav, css, js, js_after). 선택적으로 `<!--head-extra-->…<!--/head-extra-->` 에 JSON-LD·페이지 전용 `<style>`
  - `src/partials/head.html`, `header.html`, `footer.html`: 공통 head, 헤더(네비 포함), 푸터·스크립트. 연락처·주소·저작권 문구는 여기 한 곳만 수정
  - `build.py`: 네비게이션 목록(`NAV`), CSS/JS 경로 목록(`CSS`, `JS`), 캐시 버전(`VERSION`, `VERSION_OVERRIDE`). 새 페이지는 `src/pages/`에 파일 추가 + `NAV`에 항목 추가 + `sitemap.xml` 등록
  - `python3 build.py --check` 는 루트 HTML이 최신 빌드와 다르면 종료 코드 1. 커밋 전에 실행해 생성 파일 누락을 막을 것
- 배포는 그대로 생성된 루트 `*.html`을 `scp`로 올린다. `src/`, `build.py`는 서버에 올리지 않음

## 문의 폼 / 애널리틱스 / 지도

- 문의 폼: `contact.html` → `js/theme-script.js`의 `contactform()`이 `php/contact.php`로 AJAX POST, 응답은 항상 JSON. From은 `noreply@taeyang1000.com`(도메인 SPF에 서버 IP가 등록되어 있음), Reply-To는 문의자 이메일, 수신은 `taeyangcheun@naver.com`
- 문의 폼의 `site_type`(현장 유형 select, 필수)·`location`(현장 위치, 선택)은 문의 분류용. 유형 key 목록은 `contact.html`의 option 과 `php/contact.php`의 `$siteTypes` 두 곳에 있으며 `lib.php`의 `gallery_record_types`와 같은 key를 씀. `contact.html?type=parking` 처럼 `?type=` 으로 미리 선택되므로 시공 사례 섹션·블로그·광고 링크에 활용 (2026-09-19)
- `php/contact.php`는 로컬에 PHP CLI가 없어 문법 검사를 못 함. `contact_next.php`로 올린 뒤 허니팟 필드 `website`를 채운 POST(메일을 보내지 않고 success JSON만 반환)로 200을 확인하고 `mv`로 교체할 것
- 검색엔진 소유 확인 메타태그는 `build.py`의 `SITE_VERIFICATION`(네이버 서치어드바이저 / Google Search Console HTML 태그 content 값)에 넣고 빌드. 비어 있으면 출력 안 됨
- 푸터 외부 채널(유튜브·네이버 블로그 등) 링크는 `build.py`의 `SOCIAL` 목록에 추가하고 빌드
- 모바일(lg 미만)에서는 `footer.html`의 `.call-bar`(전화 상담 / 온라인 문의) 하단 고정 바가 모든 페이지에 표시됨. 스타일은 `css/default-theme.css` 끝부분
- GA4 측정 ID가 설정되면 전화 링크 클릭(`phone_call`, `js/analytics.js`)과 문의 폼 성공(`generate_lead`, `site_type` 파라미터, `theme-script.js`)이 이벤트로 기록됨. GA4에서 두 이벤트를 전환으로 표시하면 됨
- 스팸 방지: 숨김 필드 `website`(허니팟, 채워져 있으면 성공한 척 응답 후 버림), 동일 출처 검사, IP당 1시간 5건 제한(`sys_get_temp_dir()` 파일)
- Google Analytics: `js/analytics.js`의 `GA4_ID`에 측정 ID(`G-...`)를 넣으면 전 페이지 활성화. 비어 있으면 아무것도 로드하지 않음. 예전 UA-127663147-1은 2023-07 수집 종료로 제거함 (2026-09-18)
- 연락처 지도: API 키 없이 동작하는 Google Maps 임베드 iframe(`output=embed`) + 네이버 지도/카카오맵 링크 버튼. 예전 `js/map.js`(Maps JavaScript API, 키 없음 → 에러)와 MailChimp용 `php/subscribe.php`, `php/MCAPI.class.php`는 2026-09-18 삭제

## 시공 실적 (DB 기반, records.html)

- `records.html`은 `js/records.js`가 `php/gallery/records_api.php`에서 공개 실적(JSON)을 받아 연도별 표로 그림. 실적이 없거나 API가 실패하면 HTML에 있는 "정리 중" 안내와 갤러리·사례 링크가 그대로 보임
- 테이블 `gallery_records`(`php/gallery/lib.php`의 `gallery_records_ensure_schema`가 자동 생성). 컬럼: `work_month`(YYYY-MM, NULL 가능), `site_name`, `location`, `client`, `site_type`, `method`, `scale`, `note`, `photo_id`(갤러리 사진 FK, 삭제 시 NULL), `is_public`
- 현장 유형(`gallery_record_types`)과 공법(`gallery_record_methods`) 목록은 lib.php에 하드코딩. 유형 key는 `cases.html`의 앵커(school/curve/slope/busstop/golf/parking/harbor)와 맞춰 두었고 highway/other 추가
- 최초 1회 시딩: 갤러리 `field` 사진 제목으로 **비공개 초안**을 만들어 둠(`gallery_settings.records_seeded`). 관리자가 시기·위치·발주처를 채우고 "공개하기"를 눌러야 사이트에 노출됨. 임의로 만든 날짜·발주처는 없음
- 관리: https://taeyang1000.com/admin/ 의 "시공 실적" 카드 (추가/수정/공개 전환/삭제). API는 `admin/api.php`의 `records`, `record_save`, `record_public`, `record_delete`
- 배포: `scp php/gallery/lib.php php/gallery/records_api.php taeyang:~/www/php/gallery/ && scp admin/* taeyang:~/www/admin/`. PHP CLI가 로컬·서버 모두 없어 문법 검사를 못 하므로, lib.php는 `lib_next.php` 같은 임시 이름으로 올려 임시 엔드포인트로 200 확인 후 교체할 것 (2026-09-18 이 방식으로 배포). admin.css/admin.js 수정 시 `admin/index.php`의 `?v=` 올리기

## 도메인 / SSL

- taeyang1000.com은 2026-09-18 가비아에서 카페24로 기관이전됨 (카페24 `나의 서비스 관리 > 도메인관리`에서 관리, 만료 2029-03-11)
- SSL은 카페24 `SSL Basic`(Let's Encrypt, apex + www 포함) 사용. 카페24가 호스팅 종료일까지 자동 갱신하므로 직접 갱신 작업 없음
- 레포 루트 `.htaccess`가 http → https, www → apex(`https://taeyang1000.com`) 301 리다이렉트를 담당하며 `~/www/.htaccess`로 배포됨 (www 통합은 2026-09-19, 배포 전 서버 사본 `~/.htaccess.bak-20260919`). 인증서가 없는 상태에서 올리면 사이트가 끊기므로 주의

## 갤러리 관리 (DB 기반 사진 업로드)

- 갤러리(`index.html` 최신 9장, `project.html` 전체)는 `js/gallery.js`가 `php/gallery/api.php`에서 JSON을 받아 그림. HTML에 남아 있는 하드코딩 항목은 API 실패 시 폴백용
- DB: 카페24 MariaDB(`localhost`, DB/계정 `qudgk02`). 테이블 `gallery_photos`, `gallery_settings`는 `php/gallery/lib.php`가 첫 접속 때 자동 생성하고, 비어 있으면 `php/gallery/seed.php`(기존 25장)를 넣음
- 설정: 서버의 `php/gallery/config.php` (커밋 금지, `config.sample.php` 참고). DB 비밀번호와 쿠키 서명용 `secret`이 들어 있음. 권한 600
- 관리자 페이지: 레포 `admin/` → 서버 `~/www/admin/` (https://taeyang1000.com/admin/). 배포: `scp admin/* taeyang:~/www/admin/`
- 관리자 인증: 최초 접속 시 비밀번호 설정 → `gallery_settings`에 해시 저장. 로그인 쿠키 30일, 5회 실패 시 15분 잠금, 비밀번호 변경 시 기존 쿠키 전부 무효화
- 업로드 이미지는 `images/uploads/YYYY/MM/`에 `*.jpg`(긴 변 1600) + `*_t.jpg`(600x600 썸네일)로 저장. GD로 재인코딩하므로 원본 메타데이터는 남지 않음. 이 폴더는 `.gitignore`로 제외되며 서버에만 존재 → 백업 시 반드시 포함
- 기존 `images/portfolio/` 사진은 DB에 경로만 등록되어 있고, 관리자 페이지에서 삭제해도 파일은 지우지 않음 (업로드 폴더 안의 파일만 실제 삭제)
- 로그인 잠금 해제 / 비밀번호 초기화는 SSH에서 mysql CLI로 (`gallery_settings` 테이블). DB 비밀번호는 서버 `config.php`에서 읽어 서버 안에서만 사용:

```bash
ssh taeyang 'PW=$(sed -n "s/.*'"'"'pass'"'"' => '"'"'\([^'"'"']*\)'"'"'.*/\1/p" ~/www/php/gallery/config.php); mysql -u qudgk02 -p"$PW" qudgk02 -e "UPDATE gallery_settings SET v=0 WHERE k IN (\"login_lock_until\",\"login_fail_count\")"'
# 비밀번호를 아예 초기화(다음 접속 때 다시 설정 화면): ... -e "DELETE FROM gallery_settings WHERE k=\"admin_password_hash\""
```
- 관리자 페이지는 `Referrer-Policy: same-origin`을 써야 함. `no-referrer`로 두면 브라우저가 폼 POST에 `Origin: null`을 보내 동일 출처 검사에 걸림 (2026-09-18 실제로 겪은 버그)

## 성능 / SEO 규칙 (2026-09-18)

- 각 HTML은 실제로 쓰는 CSS/JS만 로드함 (페이지 메타의 `css:`/`js:` 목록. 예: slit-slider·modernizr는 `index.html`만, jarallax는 서브페이지만, contact-form은 `contact.html`만). `themify-icons`는 모바일 메뉴 아이콘(`.navbar-toggler-icon`, `responsive.css`)이 쓰므로 공통 CSS에 포함 (2026-09-19 서브페이지에서 아이콘이 깨지던 문제 수정). `js/theme-script.js`는 플러그인이 없으면 건너뛰도록 가드가 있으므로 새 페이지에 플러그인을 빼도 오류 없음
- CSS/JS 링크에는 `?v=YYYYMMDD` 버전 문자열이 붙어 있음. `.htaccess`가 css/js 7일, 이미지 30일 브라우저 캐시를 걸므로 CSS/JS를 수정하면 5개 HTML의 `?v=` 값을 함께 올릴 것
- 이미지는 커밋 전에 긴 변 1920px(갤러리 large는 1600px), JPEG 품질 82 정도로 줄여서 넣기. 원본 촬영 파일을 그대로 올리지 말 것
- 갤러리 그리드의 폴백 `<img>`에는 `loading="lazy"`를 넣지 말 것 (isotope가 높이를 계산하기 전에 로드돼야 함). 그 외 본문 이미지는 lazy 사용
- 페이지별 `<title>`/`description`/canonical/OG 태그는 `src/pages/*.html` 메타 블록에서 채워지고 `build.py`가 생성. 페이지를 추가하면 메타 블록을 채우고 `sitemap.xml`에도 URL 추가
- `cases.html`(현장 유형별 시공 사례)은 `images/portfolio/field/` 사진을 앵커 `#school #curve #slope #busstop #golf #parking #harbor` 7개 섹션으로 묶은 정적 페이지. 페이지 전용 CSS는 `src/pages/cases.html`의 `<!--head-extra-->` 안 `<style>`. 갤러리 DB와 연동되지 않으므로 사례를 추가하려면 `src/pages/cases.html` 수정 후 빌드
- 교통사고 통계 문구(`index.html` 아코디언, `about.html`)는 2024년 사망자 2,521명·10만 명당 5.3명, 2025년 2,549명 기준(2026-09-18 갱신). 매년 초 도로교통공단 발표 후 갱신
- `.htaccess` 보안 헤더: HSTS(1년, includeSubDomains 없음), nosniff, X-Frame-Options SAMEORIGIN, Permissions-Policy. Referrer-Policy 는 정적 파일에만 걸어 `admin/index.php` 의 PHP 헤더와 충돌하지 않게 함
- 로컬 미리보기: `.claude/launch.json`의 `static` (python http.server 8765). PHP(갤러리 API, 문의 폼)는 로컬에서 동작하지 않고 HTML 폴백만 보임
