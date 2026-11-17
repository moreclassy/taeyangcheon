/* 갤러리를 DB(php/gallery/api.php)에서 읽어 그린다.
 * .grid.popup-gallery[data-gallery] 요소를 채우며, data-gallery-limit 이 있으면 최신 N개만.
 * API 호출이 실패하면 HTML에 남겨둔 기존 항목이 그대로 보인다. */
(function ($) {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function itemHtml(it) {
    return '<div class="grid-item ' + esc(it.class) + '">' +
      '<div class="portfolio-item">' +
        '<img src="' + esc(it.thumb) + '" alt="' + esc(it.title) + '" width="600" height="600" loading="lazy">' +
        '<div class="portfolio-hover">' +
          '<div class="portfolio-title"> <span>' + esc(it.span) + '</span>' +
            '<h3>' + esc(it.title) + '</h3>' +
          '</div>' +
          '<div class="portfolio-icon">' +
            '<a class="popup popup-img" href="' + esc(it.large) + '" title="' + esc(it.title) + '" aria-label="크게 보기: ' + esc(it.title) + '"> <i class="flaticon-magnifier" aria-hidden="true"></i></a>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  // 현재 선택된 필터(전체/보유장비/그루빙 시공)를 새로 그린 항목에도 적용 (theme-script.js 의 applyGalleryFilter)
  function relayout($grid) {
    if (typeof window.applyGalleryFilter === 'function') window.applyGalleryFilter($grid);
  }

  $(function () {
    var $grid = $('.grid.popup-gallery[data-gallery]');
    if (!$grid.length) return;
    var limit = parseInt($grid.attr('data-gallery-limit'), 10);
    var url = 'php/gallery/api.php' + (limit > 0 ? '?limit=' + limit : '');

    $.getJSON(url).done(function (res) {
      if (!res || !res.items || !res.items.length) return;
      $grid.html(res.items.map(itemHtml).join(''));
      relayout($grid);
    });
  });
})(jQuery);
