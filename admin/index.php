<?php
/**
 * 갤러리 관리자 페이지 (폰 최적화).
 * 서버 경로: ~/www/admin/ (https://taeyang1000.com/admin/)
 *   최초 접속  → 비밀번호 설정
 *   미로그인   → 로그인 (5회 실패 시 15분 잠금)
 *   로그인 후  → 업로드 / 목록 관리 / 시공 실적 관리
 */
declare(strict_types=1);
require __DIR__ . '/../php/gallery/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin'); // no-referrer 로 두면 브라우저가 폼 POST 에 Origin: null 을 보내 same_origin_post() 가 실패함

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function same_origin_post(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    $sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($sfs !== '' && !in_array($sfs, ['same-origin', 'none'], true)) return false;
    // Origin 이 'null' 인 경우(referrer 정책 등)는 Sec-Fetch-Site 판정에 맡긴다
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== 'null' && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) return false;
    return true;
}
function redirect_self(): never
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}

$fatal = null;
try {
    gallery_db();
} catch (Throwable $e) {
    $fatal = $e->getMessage();
}

$mode  = 'admin';
$error = null;

if ($fatal === null) {
    if (gallery_admin_hash() === null) {
        $mode = 'setup';
        if (same_origin_post() && ($_POST['action'] ?? '') === 'setup') {
            $pw  = (string) ($_POST['password'] ?? '');
            $pw2 = (string) ($_POST['password2'] ?? '');
            if (mb_strlen($pw) < 8) {
                $error = '비밀번호는 8자 이상이어야 합니다.';
            } elseif ($pw !== $pw2) {
                $error = '두 비밀번호가 서로 다릅니다.';
            } else {
                gallery_admin_set_password($pw);
                gallery_admin_login_cookie();
                redirect_self();
            }
        }
    } elseif (!gallery_admin_logged_in()) {
        $mode = 'login';
        if (same_origin_post() && ($_POST['action'] ?? '') === 'login') {
            $lock = gallery_admin_locked_seconds();
            if ($lock > 0) {
                $error = sprintf('로그인 시도가 너무 많습니다. %d분 후 다시 시도하세요.', (int) ceil($lock / 60));
            } elseif (gallery_admin_verify((string) ($_POST['password'] ?? ''))) {
                gallery_admin_login_cookie();
                redirect_self();
            } else {
                $lock = gallery_admin_locked_seconds();
                $error = $lock > 0
                    ? sprintf('5회 실패로 %d분간 잠겼습니다.', (int) ceil($lock / 60))
                    : '비밀번호가 맞지 않습니다.';
            }
        }
    }
}

$categories = $fatal === null ? gallery_categories() : [];
$recordTypes = $fatal === null ? gallery_record_types() : [];
$recordMethods = $fatal === null ? gallery_record_methods() : [];
$csrf = ($fatal === null && $mode === 'admin') ? gallery_csrf_token() : '';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0f3460">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="태양천 갤러리">
<link rel="apple-touch-icon" href="../images/logo-m.png">
<link rel="icon" href="../images/favicon.ico">
<title>태양천 갤러리·실적 관리</title>
<link rel="stylesheet" href="admin.css?v=2">
</head>
<body data-mode="<?= h($mode) ?>" data-csrf="<?= h($csrf) ?>" data-categories='<?= h(json_encode($categories, JSON_UNESCAPED_UNICODE)) ?>' data-record-types='<?= h(json_encode($recordTypes, JSON_UNESCAPED_UNICODE)) ?>' data-record-methods='<?= h(json_encode($recordMethods, JSON_UNESCAPED_UNICODE)) ?>'>

<header class="topbar">
  <div class="brand"><img src="../images/logo-m.png" alt=""><span>태양천 갤러리·실적 관리</span></div>
  <?php if ($mode === 'admin'): ?>
  <nav>
    <a href="../project.html" target="_blank" rel="noopener">사이트 보기</a>
    <button type="button" id="logout" class="link">로그아웃</button>
  </nav>
  <?php endif; ?>
</header>

<main class="wrap">

<?php if ($fatal !== null): ?>
  <section class="card">
    <h2>설정 오류</h2>
    <p class="error"><?= h($fatal) ?></p>
    <p class="muted">서버의 <code>php/gallery/config.php</code> 내용(DB 비밀번호, secret)을 확인하세요.</p>
  </section>

<?php elseif ($mode === 'setup'): ?>
  <section class="card narrow">
    <h2>관리자 비밀번호 설정</h2>
    <p class="muted">처음 접속입니다. 이 페이지에서 사용할 비밀번호를 정해주세요. (8자 이상)</p>
    <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="setup">
      <label>비밀번호<input type="password" name="password" minlength="8" required autocomplete="new-password" autofocus></label>
      <label>비밀번호 확인<input type="password" name="password2" minlength="8" required autocomplete="new-password"></label>
      <button type="submit" class="primary">설정하고 시작</button>
    </form>
  </section>

<?php elseif ($mode === 'login'): ?>
  <section class="card narrow">
    <h2>로그인</h2>
    <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label>비밀번호<input type="password" name="password" required autocomplete="current-password" autofocus></label>
      <button type="submit" class="primary">로그인</button>
    </form>
    <p class="muted small">로그인하면 이 기기에서 30일간 유지됩니다.</p>
  </section>

<?php else: ?>
  <section class="card" id="upload-card">
    <h2>사진 올리기</h2>
    <label class="drop" id="drop">
      <input type="file" id="files" accept="image/*" multiple>
      <span class="drop-icon">📷</span>
      <span class="drop-text">사진 선택 또는 촬영</span>
      <span class="muted small">여러 장을 한 번에 올릴 수 있어요</span>
    </label>
    <div id="previews" class="previews"></div>
    <div class="field">
      <span class="field-label">구분</span>
      <div class="chips" id="cat-chips" role="radiogroup"></div>
    </div>
    <div class="field">
      <label class="field-label" for="title">제목 <span class="muted small">(선택한 사진 모두에 적용, 나중에 개별 수정 가능)</span></label>
      <input type="text" id="title" placeholder="예: 학교 앞 안전지대" maxlength="200" enterkeyhint="done">
    </div>
    <button type="button" id="upload" class="primary big" disabled>올리기</button>
    <div id="upload-status" class="status" aria-live="polite"></div>
  </section>

  <section class="card">
    <div class="row-between">
      <h2>등록된 사진 <span id="count" class="badge"></span></h2>
    </div>
    <div class="chips" id="list-filter"></div>
    <div id="list" class="photos"><p class="muted">불러오는 중…</p></div>
  </section>

  <section class="card" id="records-card">
    <div class="row-between">
      <h2>시공 실적 <span id="rec-count" class="badge"></span></h2>
      <button type="button" id="rec-add" class="link">+ 실적 추가</button>
    </div>
    <p class="muted small">공개로 표시한 실적만 사이트의 <a href="../records.html" target="_blank" rel="noopener">시공 실적</a> 페이지에 나옵니다. 처음에는 갤러리 사진에서 만든 비공개 초안이 들어 있으니 시기·위치·발주처를 채운 뒤 공개로 바꿔주세요.</p>
    <form id="rec-form" class="rec-form" hidden autocomplete="off">
      <input type="hidden" name="id" value="0">
      <label>현장명 *<input type="text" name="site_name" maxlength="200" required placeholder="예: ○○초등학교 앞 어린이보호구역" enterkeyhint="next"></label>
      <div class="grid2">
        <label>시공 시기<input type="month" name="work_month"></label>
        <label>현장 유형<select name="site_type"></select></label>
        <label>위치<input type="text" name="location" maxlength="200" placeholder="예: 경기 용인시"></label>
        <label>발주처<input type="text" name="client" maxlength="200" placeholder="예: ○○시청, ○○건설"></label>
        <label>공법<select name="method"></select></label>
        <label>규모<input type="text" name="scale" maxlength="200" placeholder="예: L=350m, 2차로"></label>
      </div>
      <label>비고<input type="text" name="note" maxlength="500" placeholder="예: 야간 시공, 장비 2대 동시 투입"></label>
      <label>대표 사진 <span class="muted small">(갤러리에 올린 사진 중 선택)</span><select name="photo_id"><option value="0">(없음)</option></select></label>
      <label class="check"><input type="checkbox" name="is_public" checked> 사이트에 공개</label>
      <div class="actions">
        <button type="submit" class="primary small-btn">저장</button>
        <button type="button" class="link" id="rec-cancel">취소</button>
      </div>
      <p id="rec-status" class="status"></p>
    </form>
    <div class="chips" id="rec-filter"></div>
    <div id="rec-list" class="records"><p class="muted">불러오는 중…</p></div>
  </section>

  <section class="card">
    <details>
      <summary>비밀번호 변경</summary>
      <form id="pw-form" autocomplete="off">
        <label>현재 비밀번호<input type="password" name="current" required autocomplete="current-password"></label>
        <label>새 비밀번호<input type="password" name="new" minlength="8" required autocomplete="new-password"></label>
        <label>새 비밀번호 확인<input type="password" name="new2" minlength="8" required autocomplete="new-password"></label>
        <button type="submit" class="primary">변경</button>
        <p id="pw-status" class="status"></p>
      </form>
    </details>
  </section>

  <template id="photo-tpl">
    <article class="photo">
      <a class="thumb" target="_blank" rel="noopener"><img alt="" loading="lazy"></a>
      <div class="meta">
        <span class="cat"></span>
        <p class="ptitle"></p>
        <div class="actions">
          <button type="button" class="link edit">수정</button>
          <button type="button" class="link top">맨 위로</button>
          <button type="button" class="link danger del">삭제</button>
        </div>
      </div>
      <form class="editor" hidden>
        <div class="chips edit-cats"></div>
        <input type="text" name="title" maxlength="200" placeholder="제목">
        <div class="actions">
          <button type="submit" class="primary small-btn">저장</button>
          <button type="button" class="link cancel">취소</button>
        </div>
      </form>
    </article>
  </template>
  <template id="record-tpl">
    <article class="record">
      <a class="thumb" target="_blank" rel="noopener"><img alt="" loading="lazy"></a>
      <div class="meta">
        <div class="rec-head"><span class="when"></span><span class="pub"></span></div>
        <p class="rname"></p>
        <p class="rsub muted small"></p>
        <div class="actions">
          <button type="button" class="link edit">수정</button>
          <button type="button" class="link toggle"></button>
          <button type="button" class="link danger del">삭제</button>
        </div>
      </div>
    </article>
  </template>
<?php endif; ?>

</main>
<div id="toast" class="toast" hidden></div>
<?php if ($mode === 'admin'): ?><script src="admin.js?v=2"></script><?php endif; ?>
</body>
</html>
