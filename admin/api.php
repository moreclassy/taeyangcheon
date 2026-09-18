<?php
/**
 * 관리자 전용 JSON API. 로그인 쿠키 + CSRF 토큰(X-CSRF-Token 헤더 또는 csrf 필드) 필요.
 *   GET  ?action=list
 *   POST action=upload   (multipart: category, title, file)
 *   POST action=update   (id, title, category)
 *   POST action=top      (id)
 *   POST action=delete   (id)
 *   POST action=password (current, new)
 *   POST action=logout
 */
declare(strict_types=1);
require __DIR__ . '/../php/gallery/lib.php';

try {
    gallery_db();
    if (!gallery_admin_logged_in()) {
        gallery_json(['error' => '로그인이 필요합니다.', 'code' => 'auth'], 401);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

    if ($method === 'GET') {
        if ($action === 'list') {
            gallery_json(['items' => gallery_list(), 'categories' => gallery_categories()]);
        }
        gallery_json(['error' => '알 수 없는 요청'], 400);
    }

    if ($method !== 'POST') {
        gallery_json(['error' => 'POST 만 허용'], 405);
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null;
    if (!gallery_csrf_check($token)) {
        gallery_json(['error' => '세션이 만료되었습니다. 페이지를 새로 고쳐주세요.', 'code' => 'csrf'], 403);
    }

    switch ($action) {
        case 'upload':
            $category = (string) ($_POST['category'] ?? '');
            $title    = (string) ($_POST['title'] ?? '');
            if (!isset($_FILES['file'])) {
                gallery_json(['error' => '파일이 없습니다. (서버 업로드 제한을 넘었을 수 있습니다)'], 400);
            }
            $item = gallery_store_upload($_FILES['file'], $category, $title);
            gallery_json(['ok' => true, 'item' => $item]);

        case 'update':
            gallery_update((int) ($_POST['id'] ?? 0), (string) ($_POST['title'] ?? ''), (string) ($_POST['category'] ?? ''));
            gallery_json(['ok' => true, 'item' => gallery_get((int) $_POST['id'])]);

        case 'top':
            gallery_move_top((int) ($_POST['id'] ?? 0));
            gallery_json(['ok' => true]);

        case 'delete':
            gallery_delete((int) ($_POST['id'] ?? 0));
            gallery_json(['ok' => true]);

        case 'password':
            $cur = (string) ($_POST['current'] ?? '');
            $new = (string) ($_POST['new'] ?? '');
            if (mb_strlen($new) < 8) {
                gallery_json(['error' => '새 비밀번호는 8자 이상이어야 합니다.'], 400);
            }
            if (!gallery_admin_verify($cur)) {
                gallery_json(['error' => '현재 비밀번호가 맞지 않습니다.'], 400);
            }
            gallery_admin_set_password($new);
            gallery_admin_login_cookie(); // 해시가 바뀌어 기존 쿠키가 무효화되므로 재발급
            gallery_json(['ok' => true, 'csrf' => gallery_csrf_token()]);

        case 'logout':
            gallery_admin_logout();
            gallery_json(['ok' => true]);

        default:
            gallery_json(['error' => '알 수 없는 요청'], 400);
    }
} catch (GalleryUserError $e) {
    gallery_json(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('[gallery admin] ' . $e->getMessage());
    gallery_json(['error' => $e->getMessage()], 500);
}
