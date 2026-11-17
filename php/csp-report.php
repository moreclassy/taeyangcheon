<?php
/**
 * CSP 위반 보고 수집 (.htaccess 의 Content-Security-Policy-Report-Only → report-uri /php/csp-report.php).
 * 차단하지 않고 기록만 하는 시험 단계(2026-11-18~)에 어떤 외부 자원이 정책에 걸리는지 모으기 위함.
 * 기록 위치는 웹 루트 밖 ~/csp-reports.log (한 줄에 JSON 하나). 256KB 를 넘으면 더 쓰지 않는다.
 * 확인: ssh taeyang 'tail -n 50 ~/csp-reports.log'
 */
declare(strict_types=1);

$logFile = dirname(__DIR__, 2) . '/csp-reports.log';
$maxSize = 256 * 1024;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input', false, null, 0, 8192);
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    exit;
}

// report-uri 형식 {"csp-report": {...}} 과 Reporting API 형식 [{"type":"csp-violation","body":{...}}] 둘 다 받는다
$reports = [];
if (isset($data['csp-report']) && is_array($data['csp-report'])) {
    $reports[] = $data['csp-report'];
} else {
    foreach ($data as $item) {
        if (is_array($item) && isset($item['body']) && is_array($item['body'])) $reports[] = $item['body'];
    }
}

$pick = static function (array $r, array $keys): string {
    foreach ($keys as $k) {
        if (isset($r[$k]) && is_scalar($r[$k])) return mb_substr((string)$r[$k], 0, 300);
    }
    return '';
};

$lines = '';
foreach (array_slice($reports, 0, 5) as $r) {
    $lines .= json_encode([
        't' => date('Y-m-d H:i:s'),
        'page' => $pick($r, ['document-uri', 'documentURL']),
        'directive' => $pick($r, ['violated-directive', 'effective-directive', 'effectiveDirective']),
        'blocked' => $pick($r, ['blocked-uri', 'blockedURL']),
        'source' => $pick($r, ['source-file', 'sourceFile']),
        'line' => $pick($r, ['line-number', 'lineNumber']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

clearstatcache();
if ($lines !== '' && (!is_file($logFile) || filesize($logFile) < $maxSize)) {
    @file_put_contents($logFile, $lines, FILE_APPEND | LOCK_EX);
}
http_response_code(204);
