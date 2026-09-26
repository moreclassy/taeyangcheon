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
  window.gtag('config', GA4_ID);
  // 링크 클릭 이벤트 (헤더·푸터·하단 문의 바·본문 공통)
  //  phone_call(전화) · file_download(소개서 PDF) · email_click(메일) · map_click(네이버/카카오 지도, 구글은 contact.html)
  //  inquiry_link(contact.html?type=…/purpose=… 로 가는 상담 링크) · kakao_chat(카카오톡 채널, 개설 후)
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.getAttribute('href');
    if (href.indexOf('tel:') === 0) {
      window.gtag('event', 'phone_call', { phone_number: href.slice(4) });
    } else if (href.indexOf('mailto:') === 0) {
      window.gtag('event', 'email_click', { email: href.slice(7) });
    } else if (/\.pdf$/.test(href)) {
      window.gtag('event', 'file_download', { file_name: href.split('/').pop(), file_extension: 'pdf' });
    } else if (/map\.naver\.com|map\.kakao\.com/.test(href)) {
      window.gtag('event', 'map_click', { map_provider: href.indexOf('naver') > -1 ? 'naver' : 'kakao' });
    } else if (/pf\.kakao\.com/.test(href)) {
      window.gtag('event', 'kakao_chat');
    } else if (/contact\.html\?/.test(href)) {
      var t = /[?&]type=([a-z0-9]+)/.exec(href), p = /[?&]purpose=([a-z0-9]+)/.exec(href);
      window.gtag('event', 'inquiry_link', { site_type: t ? t[1] : '', purpose: p ? p[1] : '', link_text: (a.textContent || '').trim().slice(0, 40) });
    }
  });
})();
