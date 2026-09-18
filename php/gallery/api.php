<?php
/**
 * 공개 갤러리 목록 API. index.html / project.html 의 js/gallery.js 가 호출한다.
 *   GET api.php            → 전체
 *   GET api.php?limit=9    → 최신 9개
 *   GET api.php?cat=field  → 구분별
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $limit = isset($_GET['limit']) ? max(0, (int) $_GET['limit']) : null;
    $cat   = isset($_GET['cat']) ? (string) $_GET['cat'] : null;
    $items = gallery_list($cat, $limit);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode(['items' => $items, 'categories' => gallery_categories()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[gallery api] ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['items' => [], 'error' => 'gallery unavailable']);
}
