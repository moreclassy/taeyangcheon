<?php
/**
 * 연락처 페이지 문의 폼 처리 (contact.html → js/theme-script.js 가 AJAX POST).
 * 응답은 항상 JSON {type: 'success'|'danger', message}.
 *
 * - From 은 taeyang1000.com 주소를 쓴다. 도메인 SPF 에 서버 IP(112.175.85.160)가 등록되어 있어
 *   네이버 주소를 From 으로 위조하던 예전 방식보다 스팸 판정을 피할 수 있다.
 * - Reply-To 는 문의자 이메일 → 받은 메일에서 바로 답장 가능.
 * - 수신자는 대표 메일 + 백업 메일 두 곳 (한 곳에서 놓쳐도 다른 곳에서 확인).
 * - 대표 메일 발송이 성공하고 문의자가 이메일을 적었으면 '접수 확인' 메일을 보낸다.
 *   확인 메일에는 문의자가 입력한 자유 글(이름·내용)을 넣지 않는다 (남의 주소로 광고 메일을 보내는 데 악용 방지).
 * - 개인정보 수집·이용 동의 체크박스(privacy=1)가 없으면 422. 처리방침은 privacy.html (보유 기간 문의 처리 완료 후 1년).
 * - 스팸 방지: 숨김 필드(website) 허니팟, 동일 출처 검사, X-Requested-With(AJAX) 검사,
 *   IP 당 1시간 5건 제한(검증 통과 건만, flock 으로 동시 전송 안전),
 *   문의내용에 한글이 한 글자도 없으면(외국어 스팸) 성공한 척 응답하고 버림.
 */
declare(strict_types=1);

$sendTo   = 'taeyangcheun@naver.com, coderbhkim@gmail.com';  // 대표 메일, 백업 수신자
$replyTo  = 'taeyangcheun@naver.com';  // 접수 확인 메일에 문의자가 회신하면 받을 주소
$from     = 'noreply@taeyang1000.com';
$fromName = '태양천 홈페이지';
$subject  = '[태양천 홈페이지] 문의';
$okMessage    = '문의가 접수되었습니다. 영업일 기준 1일 이내에 연락드리겠습니다. 급한 문의는 전화 010-3288-2253(08~22시)으로 주세요.';
$ackMessage   = ' 입력하신 이메일로 접수 확인 메일을 보냈습니다.';
$purposes = [  // contact.html 의 문의 목적 select 와 맞춘다 (?purpose=partner 로 미리 선택)
    'new' => '신규 시공 견적',
    'partner' => '하도급·장비 투입 협력',
    'docs' => '자료 요청',
    'other' => '기타',
];
$pavements = ['asphalt' => '아스팔트', 'concrete' => '콘크리트', 'color' => '컬러(미끄럼 방지) 포장'];
$timings   = ['1m' => '1개월 이내', '3m' => '1~3개월', 'later' => '3개월 이후'];
$siteTypes = [  // contact.html 의 select 와 맞춘다
    'school' => '어린이 보호구역 · 통학로',
    'curve' => '급커브 · 산악도로',
    'slope' => '급경사 이면도로 · 주택가',
    'busstop' => '버스 정류장 · 교차로',
    'golf' => '골프장 카트길',
    'parking' => '주차장 램프 · 지하주차장',
    'harbor' => '항만 · 교량 대면적',
    'highway' => '고속도로 · 국도',
    'other' => '기타',
];
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

// 개인정보 수집·이용 동의 (contact.html 의 필수 체크박스 privacy=1, 처리방침은 privacy.html)
if (($_POST['privacy'] ?? '') !== '1') {
    respond('danger', '개인정보 수집·이용에 동의해주셔야 문의를 접수할 수 있습니다. 동의하지 않으시면 전화(010-3288-2253)로 문의해주세요.', 422);
}

// 자바스크립트(jQuery AJAX)로 보낸 요청만 받는다. jQuery 는 같은 출처 요청에 X-Requested-With 를 붙인다.
// Origin·Sec-Fetch 헤더 없이 바로 POST 하는 봇을 걸러낸다 (허니팟 검사 뒤라 상태 점검용 허니팟 POST 는 헤더 없이도 200).
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    respond('danger', '문의를 보내지 못했습니다. 브라우저에서 자바스크립트를 켜고 다시 시도하시거나 전화(010-3288-2253)로 문의해주세요.', 403);
}

$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';  // 2026-11-18 확인: 프록시 뒤에서도 실제 접속자 IP 가 들어옴 (X-Forwarded-For 없음)
$now    = time();

// 입력값 검증
$name    = line_field('name', 100);
$email   = line_field('email', 200);
$phone   = line_field('phone', 50);
$siteType = line_field('site_type', 20);
$siteTypeLabel = $siteTypes[$siteType] ?? '(미선택)';
$location = line_field('location', 100);
$org      = line_field('org', 100);
$purpose  = line_field('purpose', 20);
$purposeLabel  = $purposes[$purpose] ?? $purposes['new'];
$pavementLabel = $pavements[line_field('pavement', 20)] ?? '(미선택)';
$timingLabel   = $timings[line_field('timing', 20)] ?? '(미정)';
$scale    = line_field('scale', 50);
$message = $_POST['message'] ?? '';
$message = is_string($message) ? mb_substr(trim(str_replace(["\r\n", "\r"], "\n", $message)), 0, 5000) : '';

if ($name === '' || $message === '') {
    respond('danger', '이름과 문의내용을 입력해주세요.', 422);
}
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respond('danger', '올바른 이메일 주소를 입력해주세요.', 422);
}

// 한국어가 아닌 문의는 무시: 문의내용에 한글(완성형·호환 자모·조합형 자모)이 없으면 봇과 같이 성공한 척 응답하고 버린다.
if (!preg_match('/[\x{AC00}-\x{D7A3}\x{3131}-\x{318E}\x{1100}-\x{11FF}]/u', $message)) {
    respond('success', $okMessage);
}

// 메일 본문
$body = "홈페이지 문의 폼으로 새 문의가 접수되었습니다.\n"
      . "=============================\n"
      . "문의 목적: {$purposeLabel}\n"
      . "이름: {$name}\n"
      . "소속·직책: " . ($org !== '' ? $org : '(미입력)') . "\n"
      . "이메일: " . ($email !== '' ? $email : '(미입력)') . "\n"
      . "전화번호: " . ($phone !== '' ? $phone : '(미입력)') . "\n"
      . "현장 유형: {$siteTypeLabel}\n"
      . "현장 위치: " . ($location !== '' ? $location : '(미입력)') . "\n"
      . "포장 종류: {$pavementLabel}\n"
      . "규모: " . ($scale !== '' ? $scale : '(미입력)') . "\n"
      . "희망 시기: {$timingLabel}\n"
      . "\n문의내용:\n{$message}\n"
      . "\n-----------------------------\n"
      . "접수 시각: " . date('Y-m-d H:i:s') . "\n"
      . "개인정보 수집·이용 동의: 동의함 (보유 기간: 문의 처리 완료 후 1년)\n"
      . "IP: {$ip}\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'B') . " <{$from}>",
];
if ($email !== '') {
    $headers[] = 'Reply-To: ' . $email;
}
$encSubject = mb_encode_mimeheader(
    $subject . ' - ' . $purposeLabel . ' - ' . ($siteTypes[$siteType] ?? '일반') . ' - ' . $name . ($org !== '' ? " ({$org})" : ''),
    'UTF-8', 'B');

// IP 당 전송 횟수 제한: 파일 잠금 안에서 읽기·검사·자리 예약을 한 번에 해 동시 전송에도 한도를 넘지 않게 한다.
// 검증을 통과한 문의만 센다 (입력 오류로 다시 보내는 사람은 한도를 쓰지 않음). 메일 발송이 실패하면 예약을 되돌린다.
$rlFile = sys_get_temp_dir() . '/taeyang_contact_' . md5($ip);
$rl = @fopen($rlFile, 'c+');
if ($rl !== false && flock($rl, LOCK_EX)) {
    $recent = [];
    foreach (preg_split('/\R/', (string)stream_get_contents($rl)) ?: [] as $line) {
        if ((int)$line > $now - $rateLimitWin) $recent[] = (int)$line;
    }
    if (count($recent) >= $rateLimitMax) {
        flock($rl, LOCK_UN);
        fclose($rl);
        respond('danger', '문의가 너무 자주 접수되었습니다. 잠시 후 다시 시도하시거나 전화로 문의해주세요.', 429);
    }
    $write = static function (array $times) use ($rl): void {
        ftruncate($rl, 0);
        rewind($rl);
        fwrite($rl, $times ? implode("\n", $times) . "\n" : '');
        fflush($rl);
    };
    $write(array_merge($recent, [$now]));
} else {
    if ($rl !== false) fclose($rl);
    $rl = false;  // 잠금 파일을 못 열면 제한 없이 진행 (문의를 놓치는 것보다 낫다)
}

// -f 로 봉투 발신자(Return-Path)도 도메인 주소로 맞춰 SPF 가 통과되게 한다. 거부되면 기본값으로 재시도.
$sent = @mail($sendTo, $encSubject, $body, implode("\r\n", $headers), '-f' . $from)
     || mail($sendTo, $encSubject, $body, implode("\r\n", $headers));

if ($rl !== false) {
    if (!$sent) $write($recent);  // 발송 실패 시 예약한 자리를 돌려준다
    flock($rl, LOCK_UN);
    fclose($rl);
}

if (!$sent) {
    respond('danger', '메일 전송에 실패했습니다. 전화(010-3288-2253)로 문의해주시면 빠르게 답변드리겠습니다.', 500);
}

// 문의자에게 접수 확인 메일 (선택 목록 값과 시각만 넣고 자유 입력은 넣지 않음)
$ackSent = false;
if ($email !== '') {
    $ackBody = "태양천 그루빙에 문의해 주셔서 감사합니다.\n\n"
             . "아래와 같이 문의가 접수되었습니다. 영업일 기준 1일 이내에 담당자가 연락드리겠습니다.\n\n"
             . "- 접수 시각: " . date('Y-m-d H:i') . "\n"
             . "- 문의 목적: {$purposeLabel}\n"
             . "- 현장 유형: {$siteTypeLabel}\n\n"
             . "급한 문의는 전화 010-3288-2253 (08:00~22:00)으로 연락 주세요.\n"
             . "현장 사진이 있으면 이 메일에 회신해 보내주셔도 됩니다.\n\n"
             . "태양천 그루빙\n"
             . "https://taeyang1000.com\n\n"
             . "※ 문의하신 적이 없다면 이 메일은 무시하셔도 됩니다.\n";
    $ackHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . mb_encode_mimeheader('태양천 그루빙', 'UTF-8', 'B') . " <{$from}>",
        'Reply-To: ' . $replyTo,
    ];
    $ackSubject = mb_encode_mimeheader('[태양천 그루빙] 문의 접수 확인', 'UTF-8', 'B');
    $ackSent = @mail($email, $ackSubject, $ackBody, implode("\r\n", $ackHeaders), '-f' . $from);
}

respond('success', $okMessage . ($ackSent ? $ackMessage : ''));
