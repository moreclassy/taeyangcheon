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
- 공통 CSS/JS(`css/default-theme.css`, `js/theme-script.js`)를 고치면 5개 HTML의 링크 뒤 `?v=YYYYMMDD` 버전 문자열도 함께 올려야 방문자 브라우저 캐시가 갱신됨

## 문의 폼 / 애널리틱스 / 지도

- 문의 폼: `contact.html` → `js/theme-script.js`의 `contactform()`이 `php/contact.php`로 AJAX POST, 응답은 항상 JSON. From은 `noreply@taeyang1000.com`(도메인 SPF에 서버 IP가 등록되어 있음), Reply-To는 문의자 이메일, 수신은 `taeyangcheun@naver.com`
- 스팸 방지: 숨김 필드 `website`(허니팟, 채워져 있으면 성공한 척 응답 후 버림), 동일 출처 검사, IP당 1시간 5건 제한(`sys_get_temp_dir()` 파일)
- Google Analytics: `js/analytics.js`의 `GA4_ID`에 측정 ID(`G-...`)를 넣으면 전 페이지 활성화. 비어 있으면 아무것도 로드하지 않음. 예전 UA-127663147-1은 2023-07 수집 종료로 제거함 (2026-09-18)
- 연락처 지도: API 키 없이 동작하는 Google Maps 임베드 iframe(`output=embed`) + 네이버 지도/카카오맵 링크 버튼. 예전 `js/map.js`(Maps JavaScript API, 키 없음 → 에러)와 MailChimp용 `php/subscribe.php`, `php/MCAPI.class.php`는 2026-09-18 삭제

## 도메인 / SSL

- taeyang1000.com은 2026-09-18 가비아에서 카페24로 기관이전됨 (카페24 `나의 서비스 관리 > 도메인관리`에서 관리, 만료 2029-03-11)
- SSL은 카페24 `SSL Basic`(Let's Encrypt, apex + www 포함) 사용. 카페24가 호스팅 종료일까지 자동 갱신하므로 직접 갱신 작업 없음
- 레포 루트 `.htaccess`가 http → https 301 리다이렉트를 담당하며 `~/www/.htaccess`로 배포됨. 인증서가 없는 상태에서 올리면 사이트가 끊기므로 주의

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

- 각 HTML은 실제로 쓰는 CSS/JS만 로드함 (예: slit-slider·modernizr는 `index.html`만, jarallax는 서브페이지만, contact-form.js는 `contact.html`만). `js/theme-script.js`는 플러그인이 없으면 건너뛰도록 가드가 있으므로 새 페이지에 플러그인을 빼도 오류 없음
- CSS/JS 링크에는 `?v=YYYYMMDD` 버전 문자열이 붙어 있음. `.htaccess`가 css/js 7일, 이미지 30일 브라우저 캐시를 걸므로 CSS/JS를 수정하면 5개 HTML의 `?v=` 값을 함께 올릴 것
- 이미지는 커밋 전에 긴 변 1920px(갤러리 large는 1600px), JPEG 품질 82 정도로 줄여서 넣기. 원본 촬영 파일을 그대로 올리지 말 것
- 갤러리 그리드의 폴백 `<img>`에는 `loading="lazy"`를 넣지 말 것 (isotope가 높이를 계산하기 전에 로드돼야 함). 그 외 본문 이미지는 lazy 사용
- 페이지별 `<title>`/`description`/canonical/OG 태그가 있으니 페이지를 추가하면 같이 채우고 `sitemap.xml`에도 URL 추가
- 로컬 미리보기: `.claude/launch.json`의 `static` (python http.server 8765). PHP(갤러리 API, 문의 폼)는 로컬에서 동작하지 않고 HTML 폴백만 보임
