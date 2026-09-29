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
- 공통 CSS/JS(`css/default-theme.css`, `js/theme-script.js`)를 고치면 `build.py`의 `VERSION`/`VERSION_OVERRIDE`를 올리고 다시 빌드해야 방문자 브라우저 캐시가 갱신됨 (css/js 는 1년·immutable 캐시라 안 올리면 1년 동안 옛 파일이 보임). `js/analytics.js` 는 `head.html` 의 `?v=` 를 직접 올림
- 서버 설정 파일 백업: `.htaccess` 를 바꿀 때는 서버에서 `cp ~/www/.htaccess ~/www/.htaccess.bak-YYYYMMDD` 후 올리고 바로 `curl -I` 로 200·301·404 를 확인 (2026-09-26 배포 전 사본 `~/www/.htaccess.bak-20260926`, 전체 백업 `backup/www-backup-20260926.tar.gz`)

## 페이지 편집 / 빌드 (헤더·푸터 공통화)

- 루트의 `*.html` 8개(404 포함)는 **생성 파일**이다. 직접 고치지 말고 `src/`를 고친 뒤 `python3 build.py`를 실행해 다시 만든다 (PHP 없이 정적 파일로 배포하기 위해 빌드 방식 사용. 로컬·서버 모두 PHP CLI 없음)
  - `src/pages/<이름>.html`: 페이지 본문(`<!--header end-->`~`<!--footer start-->` 사이) + 맨 위 `<!--page ... -->` 메타 블록(title, description, canonical, og_image, preload, nav, css, js, js_after, cta, noindex, abs_paths). 선택적으로 `<!--head-extra-->…<!--/head-extra-->` 에 JSON-LD·페이지 전용 `<style>`
  - 본문은 빌드 때 `<main id="content" tabindex="-1">` 로 감싸짐(헤더의 '본문 바로가기' 대상). 메타 `cta: yes` 면 본문 끝에 `src/partials/cta.html`(공통 상담 밴드)이 들어감(about·info·project). `nav: none` 은 활성 메뉴 없음
  - `src/pages/404.html`: `noindex: yes`(robots noindex, sitemap 제외) + `abs_paths: yes`(모든 상대 경로를 `/css/…` 처럼 절대 경로로 바꿔 `/없는/경로/` 에서도 깨지지 않음). `.htaccess` 의 `ErrorDocument 404 /404.html` 이 사용
  - `src/partials/head.html`, `header.html`, `footer.html`, `cta.html`: 공통 head, 헤더(네비·SVG 아이콘 스프라이트·데스크톱 전화 버튼 포함), 푸터(연락처 한 줄 + 사업자 정보)·스크립트, 공통 상담 밴드. 연락처·주소·사업자 정보·저작권 문구는 여기 한 곳만 수정 (JSON-LD 는 `src/pages/index.html`, 소개서는 `brochure.html` 에도 있음)
  - 사업자 정보(푸터·JSON-LD·소개서 공통): 상호 태양천 그루빙(개인사업자), 대표 김병하, 사업자등록번호 575-59-00157, 설립 2017-10-11. 건설업 등록번호·개인정보처리방침은 아직 없음 (2026-09-26)
  - `build.py`: 네비게이션 목록(`NAV`), CSS/JS 경로 목록(`CSS`, `JS`), 캐시 버전(`VERSION`, `VERSION_OVERRIDE`). 새 페이지는 `src/pages/`에 파일 추가 + `NAV`에 항목 추가 + `sitemap.xml` 등록
  - `python3 build.py --check` 는 루트 HTML이 최신 빌드와 다르면 종료 코드 1. 커밋 전에 실행해 생성 파일 누락을 막을 것
- 배포는 그대로 생성된 루트 `*.html`을 `scp`로 올린다. `src/`, `build.py`는 서버에 올리지 않음

## 문의 폼 / 애널리틱스 / 지도

- 문의 폼: `contact.html` → `js/theme-script.js`의 `contactform()`이 `php/contact.php`로 AJAX POST, 응답은 항상 JSON. From은 `noreply@taeyang1000.com`(도메인 SPF에 서버 IP가 등록되어 있음), Reply-To는 문의자 이메일, 수신은 `taeyangcheun@naver.com` + 백업 수신 `coderbhkim@gmail.com`(`$sendTo`, 쉼표 구분). 대표 메일 발송이 성공하고 문의자가 이메일을 적었으면 '[태양천 그루빙] 문의 접수 확인' 메일(Reply-To 대표 메일)을 보냄. 확인 메일에는 자유 입력(이름·내용·위치)을 넣지 않음(남의 주소로 광고 메일 보내기 악용 방지) (2026-09-26)
- 문의 폼 항목(2026-09-26): 문의 목적 `purpose`(new/partner/docs/other, `?purpose=partner` 로 미리 선택 — 푸터 '협력사·하도급 문의' 링크), 이름*, 소속·직책 `org`, 전화*, 이메일(선택·견적서 수신용), 현장 유형*, 현장 위치, 포장 종류 `pavement`, 규모 `scale`, 희망 시기 `timing`, 문의내용*. 목록 key 는 `contact.html` 과 `php/contact.php`(`$purposes`, `$pavements`, `$timings`) 두 곳. 모든 필드에 보이는 `<label>`, 입력 글자 16px(iOS 확대 방지)
- 문의 폼의 `site_type`(현장 유형 select, 필수)·`location`(현장 위치, 선택)은 문의 분류용. 유형 key 목록은 `contact.html`의 option 과 `php/contact.php`의 `$siteTypes` 두 곳에 있으며 `lib.php`의 `gallery_record_types`와 같은 key를 씀. `contact.html?type=parking` 처럼 `?type=` 으로 미리 선택되므로 시공 사례 섹션·블로그·광고 링크에 활용 (2026-09-19)
- `php/contact.php`는 로컬에 PHP CLI가 없어 문법 검사를 못 함. `contact_next.php`로 올린 뒤 허니팟 필드 `website`를 채운 POST(메일을 보내지 않고 success JSON만 반환)로 200을 확인하고 `mv`로 교체할 것
- 검색엔진 소유 확인 메타태그는 `build.py`의 `SITE_VERIFICATION`(네이버 서치어드바이저 / Google Search Console HTML 태그 content 값)에 넣고 빌드. 비어 있으면 출력 안 됨
- 푸터 외부 채널(유튜브·네이버 블로그 등) 링크는 `build.py`의 `SOCIAL` 목록에 추가하고 빌드
- 검색엔진 등록 현황: 네이버 서치어드바이저(HTML 태그 인증, 2026-09-19), Google Search Console URL 접두어 속성 `https://taeyang1000.com/`(HTML 태그 인증, sitemap.xml 제출 완료, 메인 페이지 색인 요청, 2026-09-19. 사용자의 Chrome 구글 계정으로 등록). 2026-09-07 구글 크롤링이 4xx로 실패한 이력이 있음(서버 이전 전) → 색인 보고서에서 4xx 오류가 다시 나오면 서버 상태 확인
- 모바일(lg 미만)에서는 `footer.html`의 `.call-bar`(전화 상담 / 온라인 문의) 하단 고정 바가 모든 페이지에 표시됨. 스타일은 `css/default-theme.css` 끝부분
- GA4 측정 ID가 설정되면 전화 링크 클릭(`phone_call`, `js/analytics.js`)과 문의 폼 성공(`generate_lead`, `site_type`·`purpose` 파라미터, `theme-script.js`)이 이벤트로 기록됨. GA4에서 두 이벤트를 전환으로 표시하면 됨. 그 밖에 `email_click`(mailto), `map_click`(`map_provider` naver/kakao/google), `inquiry_link`(`contact.html?type=…/purpose=…` 링크), `kakao_chat`(`pf.kakao.com` 링크, 채널 개설 후), `file_download`(PDF) (2026-09-26)
- 스팸 방지: 숨김 필드 `website`(허니팟, 채워져 있으면 성공한 척 응답 후 버림), 동일 출처 검사, IP당 1시간 5건 제한(`sys_get_temp_dir()` 파일)
- Google Analytics: `js/analytics.js`의 `GA4_ID`에 측정 ID(`G-...`)를 넣으면 전 페이지 활성화. 비어 있으면 아무것도 로드하지 않음. 예전 UA-127663147-1은 2023-07 수집 종료로 제거함 (2026-09-18). 2026-09-19 GA4 속성 생성·연결 완료: 계정 `태양천 그루빙`(a408814078), 속성 p555091674, 웹 스트림 15805900437, 측정 ID `G-0V0BMQL618`(숫자 0). 사용자의 Chrome 구글 계정 소유. 새 GA4 UI는 이벤트가 한 번 수집된 뒤에야 `관리 > 데이터 표시 > 이벤트`에서 별표로 주요 이벤트 지정 가능 → `generate_lead`, `phone_call`이 목록에 나타나면 별표 켜기. analytics.js를 고치면 `head.html`의 `js/analytics.js?v=` 올리기
- 연락처 지도: 주소 카드 + '구글 지도 열기' 버튼(facade). 버튼을 눌러야 Google Maps 임베드 iframe(`output=embed`, API 키 없음)을 넣음 → 접속만으로 472KB·구글 쿠키가 생기지 않음 (2026-09-26). 기본 동선은 아래 네이버 지도/카카오맵 링크 버튼. 예전 `js/map.js`(Maps JavaScript API, 키 없음 → 에러)와 MailChimp용 `php/subscribe.php`, `php/MCAPI.class.php`는 2026-09-18 삭제

## 시공 실적 (DB 기반, records.html)

- `records.html`은 `js/records.js`가 `php/gallery/records_api.php`에서 공개 실적(JSON)을 받아 연도별 표로 그림. 실적이 없거나 API가 실패하면 HTML에 있는 "정리 중" 안내와 갤러리·사례 링크가 그대로 보임
- 테이블 `gallery_records`(`php/gallery/lib.php`의 `gallery_records_ensure_schema`가 자동 생성). 컬럼: `work_month`(YYYY-MM, NULL 가능), `site_name`, `location`, `client`, `site_type`, `method`, `scale`, `note`, `photo_id`(갤러리 사진 FK, 삭제 시 NULL), `is_public`
- 현장 유형(`gallery_record_types`)과 공법(`gallery_record_methods`) 목록은 lib.php에 하드코딩. 유형 key는 `cases.html`의 앵커(school/curve/slope/busstop/golf/parking/harbor)와 맞춰 두었고 highway/other 추가
- 최초 1회 시딩: 갤러리 `field` 사진 제목으로 **비공개 초안**을 만들어 둠(`gallery_settings.records_seeded`). 관리자가 시기·위치·발주처를 채우고 "공개하기"를 눌러야 사이트에 노출됨. 임의로 만든 날짜·발주처는 없음
- 관리: https://taeyang1000.com/admin/ 의 "시공 실적" 카드 (추가/수정/공개 전환/삭제). API는 `admin/api.php`의 `records`, `record_save`, `record_public`, `record_delete`
- 배포: `scp php/gallery/lib.php php/gallery/records_api.php taeyang:~/www/php/gallery/ && scp admin/* taeyang:~/www/admin/`. PHP CLI가 로컬·서버 모두 없어 문법 검사를 못 하므로, lib.php는 `lib_next.php` 같은 임시 이름으로 올려 임시 엔드포인트로 200 확인 후 교체할 것 (2026-09-18 이 방식으로 배포). admin.css/admin.js 수정 시 `admin/index.php`의 `?v=` 올리기

## 홍보 자료

- `marketing/docs/웹사이트_개선_로드맵_2026-09-20.md`: 사이트 개선 로드맵(P0/P1/P2 52항목, Lighthouse 측정 근거, 대표자 확인 항목). 항목을 구현하면 문서의 해당 항목에 완료일을 적어 갱신
- `marketing/docs/사업확장_아이디어_2026-09-27.md`: 사업 확장 아이디어(6개 관점 51개 판정 → 21개 유지, 선행 게이트 G-0~G-3, 대표자 신규 질문 N1~N15). 홍보계획과 중복되는 내용은 장 번호로만 참조. 아이디어를 실행·검증하면 해당 항목에 결과와 날짜를 적어 갱신
- `marketing/docs/`: 대표자용 행정 가이드 문서(마크다운). 전문건설업 「지반조성·포장공사업」 등록 가이드(2026-09-19, 시행령 별표 2 기준. 그루빙 공사 입찰 자격의 전제 조건). 나라장터 조달업체 등록 킷(2026-09-19, 차세대 나라장터 기준. 건설업 등록 유무에 따른 A/B 경로). 법령 개정 시 문서 상단 작성일과 기준을 함께 갱신
- A4 공법 소개서: `marketing/brochure/brochure.html`(원본) → `python3 marketing/brochure/build_pdf.py` 로 `files/taeyang_grooving_brochure.pdf` 생성 (headless Chrome 사용, 한 장 초과 시 종료 코드 1). 사진은 레포 `images/` 를 긴 변 520px·품질 66 으로 줄인 임시 사본으로 넣음(1.57MB → 약 1.0MB, 나머지는 한글 글꼴). 하단 QR 은 `https://taeyang1000.com/cases.html?utm_source=brochure&utm_medium=print&utm_campaign=brochure` (`pip install qrcode` 필요, 없으면 QR 없이 생성). 전화번호·주소·상호·대표 등 문구는 HTML 에서 수정. `contact.html`·`cases.html` 의 "소개서 다운로드" 버튼이 이 PDF 를 가리키며(`download` 속성으로 한글 파일명 저장) GA4 `file_download` 이벤트가 기록됨. 고치면 `scp files/taeyang_grooving_brochure.pdf taeyang:~/www/files/`. `marketing/` 은 서버에 올리지 않음 (2026-09-19)

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

- 각 HTML은 실제로 쓰는 CSS/JS만 로드함 (페이지 메타의 `css:`/`js:` 목록. 예: slit-slider·modernizr는 `index.html`만, jarallax는 서브페이지만, contact-form은 `contact.html`만).
- 아이콘(2026-09-26): FontAwesome·themify 아이콘 폰트와 animate.css 는 삭제. 화살표·전화·메일·PDF·메뉴·핀 아이콘은 `header.html` 맨 위 SVG 스프라이트(`<symbol id="i-phone">` 등)를 `<svg class="icon" aria-hidden="true"><use href="#i-phone"/></svg>` 로 씀. 새 아이콘은 스프라이트에 `<symbol>` 추가. 연락처·기술력 섹션의 `flaticon-*` 은 그대로(`fonts/Flaticon.woff`·`.ttf`만 남김)
- 글꼴(2026-09-26): 웹폰트 없이 시스템 글꼴(`-apple-system, 'Apple SD Gothic Neo', 'Malgun Gothic', …`), `body{word-break:keep-all}`. Google Fonts @import 는 렌더 차단 0.9초라 제거 — 다시 넣지 말 것
- 색(2026-09-26): 흰·회색 배경 위 글자·링크는 `#a66800`(대비 4.56:1), 주황(`#f9a305`) 배경 위 글자는 `#1d1d1d`, `#f9a305` 는 면·아이콘·어두운 배경 위 글자에만. 페이지마다 `<h1>` 은 본문 제목 하나(헤더 로고는 `span.logo-text`), 그 아래 h2 → h3 순서
- 접근성(2026-09-26): 헤더 '본문 바로가기' 링크, `<main id="content">`, `:focus-visible` 3px 윤곽선(어두운 배경은 흰색), 히어로 슬라이더 자동 넘김·방향키 없음(`#nav-dots` 버튼으로 전환), `prefers-reduced-motion` 대응, 서비스 소개는 정적 카드(예전 hover 플립 카드 제거) `js/theme-script.js`는 플러그인이 없으면 건너뛰도록 가드가 있으므로 새 페이지에 플러그인을 빼도 오류 없음
- CSS/JS 링크에는 `?v=YYYYMMDD` 버전 문자열이 붙어 있음. `.htaccess`가 css/js·폰트 1년 + `immutable`, 이미지 30일 브라우저 캐시를 걸므로 CSS/JS를 수정하면 반드시 `build.py` 의 `VERSION` 을 올려 모든 HTML의 `?v=` 를 바꿀 것 (2026-09-26 7일 → 1년. 폰트는 `AddType font/woff2` 등으로 MIME 을 맞춰 캐시 규칙이 적용되게 함)
- 이미지는 커밋 전에 긴 변 1920px(갤러리 large는 1600px), JPEG 품질 82 정도로 줄여서 넣기. 원본 촬영 파일을 그대로 올리지 말 것. 어두운 오버레이(`data-overlay` 7~8)가 덮는 배경은 1280px·품질 65 로 충분 (2026-09-26 `images/bg/hero-*.jpg` 등)
- **첫 화면 이미지(히어로 배경, 서브페이지 배너, 페이지 첫 사진)에는 `loading="lazy"` 금지** — LCP 가 늦어짐. 대신 `fetchpriority="high"`(또는 메타 `preload`)와 `width`/`height` 를 넣음. 본문 `<img>` 에는 모두 `width`/`height`(실제 픽셀) 를 넣어 CLS 를 막음 (2026-09-26)
- 서브페이지 배너(`.page-title`)의 배경은 `data-bg-img` 대신 HTML `style="background-image:url(...)"` 로 넣어 JS 실행 전에 바로 칠함. jarallax 는 모바일에서 끔(`disableParallax`)
- 배경·OG 사진은 자체 현장 사진만 사용 (2026-09-26 스톡 사진 bg/03·04·05·about·project·contact, about/mission·vission·value, info/01 삭제). OG 이미지는 모든 페이지 공통 `images/og/default.jpg`(1200×630, 사진+상호+전화). 파비콘은 루트 `favicon.ico`·`apple-touch-icon.png`, `images/icon-192.png`·`icon-512.png`(임시 해+홈 마크)
- 모든 페이지의 히어로·배너 높이는 CSS 로 고정(`.fullscreen-banner{min-height:100vh}`, 히어로 글자 여백 CSS). 흰 화면 프리로더(`#ht-preloader`, `loader.gif`)는 LCP·CLS 원인이라 삭제 — 다시 넣지 말 것
- 갤러리 그리드의 폴백 `<img>`에는 `loading="lazy"`를 넣지 말 것 (isotope가 높이를 계산하기 전에 로드돼야 함). 그 외 본문 이미지는 lazy 사용
- 페이지별 `<title>`/`description`/canonical/OG 태그는 `src/pages/*.html` 메타 블록에서 채워지고 `build.py`가 생성. 페이지를 추가하면 메타 블록을 채우고 `sitemap.xml`에도 URL 추가
- `build.py`는 다시 생성된 페이지의 `sitemap.xml` `<lastmod>`를 오늘 날짜로 자동 갱신하므로 배포 시 `sitemap.xml`도 함께 올릴 것. 본문에 아코디언(`.accordion`, 현재 `index.html`)이 있으면 질문·답변을 뽑아 FAQPage JSON-LD를 자동 생성함(답변 안의 `<a>` 링크는 제외). 문구는 HTML 한 곳만 고치면 됨 (2026-09-19)
- `cases.html`(현장 유형별 시공 사례)은 `images/portfolio/field/` 사진을 앵커 `#school #curve #slope #busstop #golf #parking #harbor` 7개 섹션으로 묶은 정적 페이지. 페이지 전용 CSS는 `src/pages/cases.html`의 `<!--head-extra-->` 안 `<style>`. 갤러리 DB와 연동되지 않으므로 사례를 추가하려면 `src/pages/cases.html` 수정 후 빌드
- 교통사고 통계 문구(`index.html` 아코디언, `about.html`)는 2024년 사망자 2,521명·10만 명당 5.3명, 2025년 2,549명 기준(2026-09-18 갱신). 매년 초 도로교통공단 발표 후 갱신
- `.htaccess` 보안 헤더: HSTS(1년, includeSubDomains 없음), nosniff, X-Frame-Options SAMEORIGIN, Permissions-Policy. Referrer-Policy 는 정적 파일에만 걸어 `admin/index.php` 의 PHP 헤더와 충돌하지 않게 함
- 로컬 미리보기: `.claude/launch.json`의 `static` (python http.server 8765). PHP(갤러리 API, 문의 폼)는 로컬에서 동작하지 않고 HTML 폴백만 보임
- `robots.txt` 는 `/php/` 를 막되 `Allow: /php/gallery/api.php`, `Allow: /php/gallery/records_api.php` 를 먼저 둠(갤러리·실적 JSON 을 검색엔진이 렌더링할 수 있게). `.htaccess` 는 `hosting_index.html`(카페24 기본 파일, 내부 호스트명 노출)을 mod_rewrite 로 403 처리 (2026-09-26)
- 갤러리 DB 사진 제목 일부가 자모 분리(NFD) 한글로 들어가 있음(예전 macOS 파일명에서 복사). 검색에 불리하므로 관리자 화면에서 제목을 다시 입력하면 NFC 로 저장됨. `seed.php`·HTML 폴백은 2026-09-26 NFC 로 정리
