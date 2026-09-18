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
- `php/MCAPI.class.php`는 PHP 8 호환을 위해 `__construct`가 추가되어 있음 (원본은 PHP 4 스타일 생성자)

## 도메인 / SSL

- taeyang1000.com은 2026-09-18 가비아에서 카페24로 기관이전됨 (카페24 `나의 서비스 관리 > 도메인관리`에서 관리, 만료 2029-03-11)
- SSL은 카페24 `SSL Basic`(Let's Encrypt, apex + www 포함) 사용. 카페24가 호스팅 종료일까지 자동 갱신하므로 직접 갱신 작업 없음
- 레포 루트 `.htaccess`가 http → https 301 리다이렉트를 담당하며 `~/www/.htaccess`로 배포됨. 인증서가 없는 상태에서 올리면 사이트가 끊기므로 주의
