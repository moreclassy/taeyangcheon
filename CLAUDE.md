# taeyangcheon

태양천(taeyang1000.com) 정적 웹사이트. HTML/CSS/JS + `php/` (메일 발송용).

## 서버 접근

- 호스팅: 카페24 웹호스팅 (`uws7-087.cafe24.com`), 제한된 셸이라 `whoami` 등 일부 명령 없음
- 계정: `qudgk02`
- SSH 키: 레포 루트의 `qudgk02_key_20260918.pem` (`.gitignore`로 제외됨, 권한 600 유지)
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
