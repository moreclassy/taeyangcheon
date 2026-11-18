/* Google Analytics 4 로더.
 * GA4 측정 ID(형식: G-XXXXXXXXXX)를 아래 GA4_ID 에 넣으면 모든 페이지에서 활성화된다.
 * 비어 있으면 아무것도 로드하지 않는다. (기존 Universal Analytics UA-127663147-1 은 2023-07 수집 종료) */
(function () {
  'use strict';
  var GA4_ID = 'G-0V0BMQL618';
  if (!GA4_ID) return;
  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
  document.head.appendChild(s);
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  // 네이버 파워링크 클릭은 주소에 n_media·n_keyword·n_query 가 붙어 오는데 GA4 는 이걸 몰라서
  // 광고 클릭을 naver/organic·블로그 referral·direct 로 섞어 센다. 그래서 GA4 에 넘기는 주소에만
  // utm 을 덧붙여 광고 유입으로 집계되게 한다 (방문자가 보는 주소는 그대로).
  //  source naver / medium cpc(검색 결과) · display(블로그·카페 등 콘텐츠 지면)
  //  campaign powerlink_search · powerlink_content · powerlink_partner(다음·빙 등) · powerlink(출처 모름)
  //  term = 광고 키워드(n_keyword), content = 방문자가 실제 검색한 말(n_query, 있을 때만)
  function powerlinkLocation() {
    var q = location.search;
    if (!/[?&]n_media=/.test(q) || /[?&]utm_source=/.test(q)) return '';
    function param(k) {
      var m = new RegExp('[?&]' + k + '=([^&#]*)').exec(q);
      if (!m) return '';
      try { return decodeURIComponent(m[1].replace(/\+/g, ' ')); } catch (e) { return m[1]; }
    }
    var ref = '';
    try { ref = document.referrer ? new URL(document.referrer).hostname : ''; } catch (e) { ref = ''; }
    var medium = 'cpc', campaign = 'powerlink';
    if (/(^|\.)search\.naver\.com$/.test(ref)) campaign = 'powerlink_search';
    else if (/(^|\.)naver\.com$/.test(ref)) { medium = 'display'; campaign = 'powerlink_content'; }
    else if (ref && ref !== location.hostname) campaign = 'powerlink_partner';
    var utm = ['utm_source=naver', 'utm_medium=' + medium, 'utm_campaign=' + campaign];
    var kw = param('n_keyword'), sq = param('n_query');
    if (kw) utm.push('utm_term=' + encodeURIComponent(kw));
    if (sq) utm.push('utm_content=' + encodeURIComponent(sq));
    return location.origin + location.pathname + q + '&' + utm.join('&') + location.hash;
  }
  var adLoc = powerlinkLocation();
  window.gtag('config', GA4_ID, adLoc ? { page_location: adLoc } : {});
  // 링크가 화면의 어느 자리에 있는지 (GA4 맞춤 측정기준 button_location 으로 등록해 보고서에서 봄)
  var PLACES = [
    ['.topbar-link', '헤더 상단 연락처'],
    ['.header-cta', '헤더 전화 버튼'],
    ['.call-bar', '모바일 하단 바'],
    ['.cta-band', '상담 밴드'],
    ['footer', '푸터'],
    ['.contact-info', '연락처 카드'],
    ['.phone-tip', '문의 폼 위 안내'],
    ['.fullscreen-banner', '메인 첫 화면'],
    ['main', '본문']
  ];
  function place(a) {
    for (var i = 0; i < PLACES.length; i++) {
      if (a.closest(PLACES[i][0])) return PLACES[i][1];
    }
    return '기타';
  }
  // 링크 클릭 이벤트 (헤더·푸터·하단 문의 바·본문 공통). 모든 이벤트에 button_location 이 붙음
  //  phone_call(전화) · file_download(소개서 PDF) · email_click(메일) · map_click(네이버/카카오 지도, 구글은 contact.html)
  //  inquiry_link(contact.html?type=…/purpose=… 로 가는 상담 링크) · kakao_chat(카카오톡 채널, 개설 후)
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.getAttribute('href');
    var loc = place(a);
    if (href.indexOf('tel:') === 0) {
      window.gtag('event', 'phone_call', { phone_number: href.slice(4), button_location: loc });
    } else if (href.indexOf('mailto:') === 0) {
      window.gtag('event', 'email_click', { email: href.slice(7), button_location: loc });
    } else if (/\.pdf$/.test(href)) {
      window.gtag('event', 'file_download', { file_name: href.split('/').pop(), file_extension: 'pdf', button_location: loc });
    } else if (/map\.naver\.com|map\.kakao\.com/.test(href)) {
      window.gtag('event', 'map_click', { map_provider: href.indexOf('naver') > -1 ? 'naver' : 'kakao', button_location: loc });
    } else if (/pf\.kakao\.com/.test(href)) {
      window.gtag('event', 'kakao_chat', { button_location: loc });
    } else if (/contact\.html\?/.test(href)) {
      var t = /[?&]type=([a-z0-9]+)/.exec(href), p = /[?&]purpose=([a-z0-9]+)/.exec(href);
      window.gtag('event', 'inquiry_link', { site_type: t ? t[1] : '', purpose: p ? p[1] : '', link_text: (a.textContent || '').trim().slice(0, 40), button_location: loc });
    }
  });
})();
