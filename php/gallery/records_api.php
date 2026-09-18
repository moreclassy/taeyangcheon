<?php
/**
 * 공개 시공 실적 API. records.html 의 js/records.js 가 호출한다.
 *   GET records_api.php → 공개(is_public=1) 실적 전체, 시공 시기 내림차순
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $items = gallery_records_list(true);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode([
        'items'   => $items,
        'types'   => gallery_record_types(),
        'methods' => gallery_record_methods(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[records api] ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['items' => [], 'error' => 'records unavailable']);
}
