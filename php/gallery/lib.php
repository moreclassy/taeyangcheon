<?php
/**
 * 갤러리 공용 라이브러리: DB 연결/스키마, 관리자 인증, 이미지 처리.
 * 관리자 페이지(admin/)와 공개 API(api.php)에서 require 한다.
 */
declare(strict_types=1);

const GALLERY_ROOT   = __DIR__ . '/../..';   // 웹 루트 (~/www)
const GALLERY_COOKIE = 'tyc_admin';
const GALLERY_COOKIE_DAYS = 30;
const GALLERY_LOGIN_MAX_FAILS = 5;
const GALLERY_LOGIN_LOCK_SEC  = 15 * 60;

/** 사용자에게 그대로 보여줘도 되는 오류 (잘못된 파일, 잘못된 입력 등) → HTTP 400 */
class GalleryUserError extends RuntimeException {}

// ---------- 설정 / DB ----------

function gallery_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('php/gallery/config.php 가 없습니다. config.sample.php 를 복사해 설정하세요.');
        }
        $cfg = require $file;
        if (empty($cfg['secret']) || $cfg['secret'] === 'CHANGE_ME') {
            throw new RuntimeException('config.php 의 secret 을 설정하세요.');
        }
    }
    return $cfg;
}

function gallery_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = gallery_config()['db'];
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        gallery_ensure_schema($pdo);
    }
    return $pdo;
}

function gallery_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_photos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category   VARCHAR(32)  NOT NULL,
        title      VARCHAR(200) NOT NULL DEFAULT '',
        thumb_path VARCHAR(255) NOT NULL,
        large_path VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_sort (sort_order, id),
        KEY idx_cat (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_settings (
        k VARCHAR(64) NOT NULL PRIMARY KEY,
        v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 최초 1회: 기존 하드코딩 사진을 DB로 옮긴다
    if (gallery_setting_get($pdo, 'seeded') === null) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM gallery_photos')->fetchColumn();
        if ($count === 0) {
            $seed = require __DIR__ . '/seed.php';
            $st = $pdo->prepare('INSERT INTO gallery_photos (category, title, thumb_path, large_path, sort_order) VALUES (?, ?, ?, ?, ?)');
            $n = count($seed);
            foreach ($seed as $i => $s) {
                $st->execute([$s['category'], $s['title'], $s['thumb'], $s['large'], $n - $i]);
            }
        }
        gallery_setting_set($pdo, 'seeded', '1');
    }
}

function gallery_setting_get(PDO $pdo, string $k): ?string
{
    $st = $pdo->prepare('SELECT v FROM gallery_settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function gallery_setting_set(PDO $pdo, string $k, ?string $v): void
{
    $st = $pdo->prepare('INSERT INTO gallery_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $st->execute([$k, $v]);
}

// ---------- 사진 목록 ----------

function gallery_categories(): array
{
    return gallery_config()['categories'];
}

function gallery_format_row(array $r): array
{
    $cats = gallery_categories();
    $c = $cats[$r['category']] ?? ['class' => '', 'label' => $r['category'], 'span' => $r['category']];
    return [
        'id'         => (int) $r['id'],
        'category'   => $r['category'],
        'class'      => $c['class'],
        'label'      => $c['label'],
        'span'       => $c['span'],
        'title'      => $r['title'],
        'thumb'      => $r['thumb_path'],
        'large'      => $r['large_path'],
        'created_at' => $r['created_at'],
    ];
}

function gallery_list(?string $category = null, ?int $limit = null): array
{
    $pdo = gallery_db();
    $sql = 'SELECT * FROM gallery_photos';
    $params = [];
    if ($category !== null && $category !== '') {
        $sql .= ' WHERE category = ?';
        $params[] = $category;
    }
    $sql .= ' ORDER BY sort_order DESC, id DESC';
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return array_map('gallery_format_row', $st->fetchAll());
}

function gallery_get(int $id): ?array
{
    $st = gallery_db()->prepare('SELECT * FROM gallery_photos WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? gallery_format_row($r) : null;
}

function gallery_next_sort_order(): int
{
    return (int) gallery_db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_photos')->fetchColumn();
}

function gallery_update(int $id, string $title, string $category): void
{
    if (!isset(gallery_categories()[$category])) {
        throw new GalleryUserError('알 수 없는 구분입니다.');
    }
    $title = trim(mb_substr($title, 0, 200));
    $st = gallery_db()->prepare('UPDATE gallery_photos SET title = ?, category = ? WHERE id = ?');
    $st->execute([$title, $category, $id]);
}

function gallery_move_top(int $id): void
{
    $st = gallery_db()->prepare('UPDATE gallery_photos SET sort_order = ? WHERE id = ?');
    $st->execute([gallery_next_sort_order(), $id]);
}

function gallery_delete(int $id): void
{
    $item = gallery_get($id);
    if (!$item) {
        return;
    }
    $st = gallery_db()->prepare('DELETE FROM gallery_photos WHERE id = ?');
    $st->execute([$id]);
    // 업로드 폴더 안의 파일만 실제 삭제 (레포에 있는 기존 이미지는 건드리지 않음)
    foreach ([$item['thumb'], $item['large']] as $rel) {
        gallery_unlink_uploaded($rel);
    }
}

function gallery_unlink_uploaded(string $rel): void
{
    $uploadDir = trim(gallery_config()['upload_dir'], '/');
    if (strpos($rel, $uploadDir . '/') !== 0) {
        return;
    }
    $root = realpath(GALLERY_ROOT);
    $abs  = realpath(GALLERY_ROOT . '/' . $rel);
    if ($root && $abs && strpos($abs, $root . DIRECTORY_SEPARATOR) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}

// ---------- 이미지 처리 / 업로드 ----------

/**
 * $_FILES 항목 하나를 받아 large/thumb 를 만들고 DB에 등록한다. 등록된 항목을 반환.
 */
function gallery_store_upload(array $file, string $category, string $title): array
{
    if (!isset(gallery_categories()[$category])) {
        throw new GalleryUserError('알 수 없는 구분입니다.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new GalleryUserError('업로드 실패 (코드 ' . $file['error'] . ')');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new GalleryUserError('잘못된 업로드입니다.');
    }

    $data = file_get_contents($file['tmp_name']);
    $img  = gallery_decode_image($data, $file['tmp_name']);

    $cfg   = gallery_config();
    $large = gallery_resize_fit($img, (int) $cfg['large_max']);
    $thumb = gallery_crop_square($img, (int) $cfg['thumb_size']);
    imagedestroy($img);

    $relDir = trim($cfg['upload_dir'], '/') . '/' . date('Y/m');
    $absDir = GALLERY_ROOT . '/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        throw new RuntimeException('업로드 폴더를 만들 수 없습니다: ' . $relDir);
    }
    $name      = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $largePath = $relDir . '/' . $name . '.jpg';
    $thumbPath = $relDir . '/' . $name . '_t.jpg';

    if (!imagejpeg($large, GALLERY_ROOT . '/' . $largePath, 85) || !imagejpeg($thumb, GALLERY_ROOT . '/' . $thumbPath, 82)) {
        @unlink(GALLERY_ROOT . '/' . $largePath);
        @unlink(GALLERY_ROOT . '/' . $thumbPath);
        throw new RuntimeException('이미지 저장에 실패했습니다.');
    }
    imagedestroy($large);
    imagedestroy($thumb);

    $title = trim(mb_substr($title, 0, 200));
    $pdo = gallery_db();
    $st  = $pdo->prepare('INSERT INTO gallery_photos (category, title, thumb_path, large_path, sort_order) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$category, $title, $thumbPath, $largePath, gallery_next_sort_order()]);

    return gallery_get((int) $pdo->lastInsertId());
}

/** 바이너리 → GD 이미지 (EXIF 회전 보정 포함). GD가 못 읽으면 Imagick 으로 재시도. */
function gallery_decode_image(string $data, string $tmpPath): GdImage
{
    $info = @getimagesizefromstring($data);
    $img  = @imagecreatefromstring($data);

    if (!$img && class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->readImageBlob($data);
            $im->autoOrient();
            $im->setImageFormat('jpeg');
            $img  = @imagecreatefromstring($im->getImageBlob());
            $info = false; // 이미 회전 보정됨
            $im->clear();
        } catch (Throwable $e) {
            $img = false;
        }
    }
    if (!$img) {
        throw new GalleryUserError('이미지 파일로 인식되지 않습니다.');
    }

    // JPEG EXIF Orientation 보정
    if ($info && ($info['mime'] ?? '') === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmpPath);
        $o = (int) ($exif['Orientation'] ?? 1);
        $rotated = null;
        if ($o === 3) {
            $rotated = imagerotate($img, 180, 0);
        } elseif ($o === 6) {
            $rotated = imagerotate($img, -90, 0);
        } elseif ($o === 8) {
            $rotated = imagerotate($img, 90, 0);
        }
        if ($rotated) {
            imagedestroy($img);
            $img = $rotated;
        }
    }
    return $img;
}

/** 흰 배경의 트루컬러 캔버스 (PNG 투명 → 흰색) */
function gallery_canvas(int $w, int $h): GdImage
{
    $c = imagecreatetruecolor($w, $h);
    imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255));
    return $c;
}

/** 긴 변이 $max 이하가 되도록 축소 (작으면 그대로 복사해서 메타데이터만 제거) */
function gallery_resize_fit(GdImage $src, int $max): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = gallery_canvas($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** 중앙 정사각 크롭 후 $size x $size */
function gallery_crop_square(GdImage $src, int $size): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $sx = (int) floor(($w - $side) / 2);
    $sy = (int) floor(($h - $side) / 2);
    $dst = gallery_canvas($size, $size);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);
    return $dst;
}

// ---------- 관리자 인증 ----------

function gallery_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function gallery_admin_hash(): ?string
{
    return gallery_setting_get(gallery_db(), 'admin_password_hash');
}

function gallery_admin_set_password(string $pw): void
{
    gallery_setting_set(gallery_db(), 'admin_password_hash', password_hash($pw, PASSWORD_DEFAULT));
}

/** 잠금 중이면 남은 초, 아니면 0 */
function gallery_admin_locked_seconds(): int
{
    $until = (int) (gallery_setting_get(gallery_db(), 'login_lock_until') ?? 0);
    return max(0, $until - time());
}

function gallery_admin_verify(string $pw): bool
{
    $pdo = gallery_db();
    if (gallery_admin_locked_seconds() > 0) {
        return false;
    }
    $hash = gallery_admin_hash();
    if ($hash !== null && password_verify($pw, $hash)) {
        gallery_setting_set($pdo, 'login_fail_count', '0');
        return true;
    }
    $fails = (int) (gallery_setting_get($pdo, 'login_fail_count') ?? 0) + 1;
    if ($fails >= GALLERY_LOGIN_MAX_FAILS) {
        gallery_setting_set($pdo, 'login_lock_until', (string) (time() + GALLERY_LOGIN_LOCK_SEC));
        $fails = 0;
    }
    gallery_setting_set($pdo, 'login_fail_count', (string) $fails);
    return false;
}

function gallery_cookie_sig(string $payload): string
{
    // 비밀번호 해시 일부를 섞어서, 비밀번호를 바꾸면 기존 로그인 쿠키가 모두 무효화되게 한다
    $tail = substr(gallery_admin_hash() ?? '', -16);
    return hash_hmac('sha256', $payload . '|' . $tail, gallery_config()['secret']);
}

function gallery_admin_login_cookie(): void
{
    $exp     = time() + GALLERY_COOKIE_DAYS * 86400;
    $payload = $exp . '.' . bin2hex(random_bytes(8));
    $value   = $payload . '.' . gallery_cookie_sig($payload);
    setcookie(GALLERY_COOKIE, $value, [
        'expires'  => $exp,
        'path'     => '/',
        'secure'   => gallery_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[GALLERY_COOKIE] = $value;
}

function gallery_admin_logout(): void
{
    setcookie(GALLERY_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => gallery_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[GALLERY_COOKIE]);
}

function gallery_admin_logged_in(): bool
{
    $raw = $_COOKIE[GALLERY_COOKIE] ?? '';
    $parts = explode('.', $raw);
    if (count($parts) !== 3) {
        return false;
    }
    [$exp, $nonce, $sig] = $parts;
    if (!ctype_digit($exp) || (int) $exp < time()) {
        return false;
    }
    return hash_equals(gallery_cookie_sig($exp . '.' . $nonce), $sig);
}

function gallery_csrf_token(): string
{
    return hash_hmac('sha256', 'csrf|' . ($_COOKIE[GALLERY_COOKIE] ?? ''), gallery_config()['secret']);
}

function gallery_csrf_check(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(gallery_csrf_token(), $token);
}

// ---------- 응답 도우미 ----------

function gallery_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
