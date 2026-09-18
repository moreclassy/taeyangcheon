<?php
/**
 * 연락처 페이지 문의 폼 처리 (contact.html → js/theme-script.js 가 AJAX POST).
 * 응답은 항상 JSON {type: 'success'|'danger', message}.
 *
 * - From 은 taeyang1000.com 주소를 쓴다. 도메인 SPF 에 서버 IP(112.175.85.160)가 등록되어 있어
 *   네이버 주소를 From 으로 위조하던 예전 방식보다 스팸 판정을 피할 수 있다.
 * - Reply-To 는 문의자 이메일 → 받은 메일에서 바로 답장 가능.
 * - 스팸 방지: 숨김 필드(website) 허니팟, 동일 출처 검사, IP 당 1시간 5건 제한.
 */
declare(strict_types=1);

$sendTo   = 'taeyangcheun@naver.com';
$from     = 'noreply@taeyang1000.com';
$fromName = '태양천 홈페이지';
$subject  = '[태양천 홈페이지] 문의';
$okMessage    = '태양천 그루빙에 문의해주셔서 감사합니다! 내용 확인 후 답장 드리도록 하겠습니다.';
$rateLimitMax = 5;     // 건
$rateLimitWin = 3600;  // 초

mb_internal_encoding('UTF-8');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(string $type, string $message, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['type' => $type, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 한 줄 필드: 제어문자 제거, 길이 제한 */
function line_field(string $key, int $max): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) return '';
    $v = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('danger', '잘못된 요청입니다.', 405);
}

// 동일 출처 검사 (다른 사이트에서 폼을 위조해 보내는 경우 차단)
$sfs = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
if ($sfs !== '' && !in_array($sfs, ['same-origin', 'none'], true)) {
    respond('danger', '잘못된 요청입니다.', 403);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== 'null' && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) {
    respond('danger', '잘못된 요청입니다.', 403);
}

// 허니팟: 사람은 볼 수 없는 필드가 채워져 있으면 봇. 성공한 척 응답하고 버린다.
if (($_POST['website'] ?? '') !== '') {
    respond('success', $okMessage);
}

// IP 당 전송 횟수 제한
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rlFile = sys_get_temp_dir() . '/taeyang_contact_' . md5($ip);
$now    = time();
$recent = [];
if (is_file($rlFile)) {
    foreach (file($rlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $t) {
        if ((int)$t > $now - $rateLimitWin) $recent[] = (int)$t;
    }
}
if (count($recent) >= $rateLimitMax) {
    respond('danger', '문의가 너무 자주 접수되었습니다. 잠시 후 다시 시도하시거나 전화로 문의해주세요.', 429);
}

// 입력값 검증
$name    = line_field('name', 100);
$email   = line_field('email', 200);
$phone   = line_field('phone', 50);
$message = $_POST['message'] ?? '';
$message = is_string($message) ? mb_substr(trim(str_replace(["\r\n", "\r"], "\n", $message)), 0, 5000) : '';

if ($name === '' || $message === '') {
    respond('danger', '이름과 문의내용을 입력해주세요.', 422);
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respond('danger', '올바른 이메일 주소를 입력해주세요.', 422);
}

// 메일 본문
$body = "홈페이지 문의 폼으로 새 문의가 접수되었습니다.\n"
      . "=============================\n"
      . "이름: {$name}\n"
      . "이메일: " . ($email !== '' ? $email : '(미입력)') . "\n"
      . "전화번호: " . ($phone !== '' ? $phone : '(미입력)') . "\n"
      . "\n문의내용:\n{$message}\n"
      . "\n-----------------------------\n"
      . "접수 시각: " . date('Y-m-d H:i:s') . "\n"
      . "IP: {$ip}\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'B') . " <{$from}>",
    'X-Mailer: PHP/' . PHP_VERSION,
];
if ($email !== '') {
    $headers[] = 'Reply-To: ' . $email;
}
$encSubject = mb_encode_mimeheader($subject . ' - ' . $name, 'UTF-8', 'B');

// -f 로 봉투 발신자(Return-Path)도 도메인 주소로 맞춰 SPF 가 통과되게 한다. 거부되면 기본값으로 재시도.
$sent = @mail($sendTo, $encSubject, $body, implode("\r\n", $headers), '-f' . $from)
     || mail($sendTo, $encSubject, $body, implode("\r\n", $headers));

if (!$sent) {
    respond('danger', '메일 전송에 실패했습니다. 전화(010-5152-2253)로 문의해주시면 빠르게 답변드리겠습니다.', 500);
}

$recent[] = $now;
@file_put_contents($rlFile, implode("\n", $recent) . "\n", LOCK_EX);

respond('success', $okMessage);
