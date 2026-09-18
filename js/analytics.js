/* Google Analytics 4 로더.
 * GA4 측정 ID(형식: G-XXXXXXXXXX)를 아래 GA4_ID 에 넣으면 모든 페이지에서 활성화된다.
 * 비어 있으면 아무것도 로드하지 않는다. (기존 Universal Analytics UA-127663147-1 은 2023-07 수집 종료) */
(function () {
  'use strict';
  var GA4_ID = '';
  if (!GA4_ID) return;
  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
  document.head.appendChild(s);
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', GA4_ID);
})();
