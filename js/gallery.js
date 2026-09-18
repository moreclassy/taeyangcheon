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
        '<img src="' + esc(it.thumb) + '" alt="' + esc(it.title) + '" loading="lazy">' +
        '<div class="portfolio-hover">' +
          '<div class="portfolio-title"> <span>' + esc(it.span) + '</span>' +
            '<h4>' + esc(it.title) + '</h4>' +
          '</div>' +
          '<div class="portfolio-icon">' +
            '<a class="popup popup-img" href="' + esc(it.large) + '" title="' + esc(it.title) + '"> <i class="flaticon-magnifier"></i></a>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function relayout($grid) {
    if (!$.fn.isotope || !$grid.data('isotope')) return; // 아직 isotope 초기화 전이면 초기화 시점에 반영됨
    var filter = $('.portfolio-filter .is-checked').attr('data-filter') || '*';
    $grid.isotope('reloadItems').isotope({ filter: filter });
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
      var pending = 0;
      $grid.find('img').each(function () {
        if (this.complete) return;
        pending++;
        $(this).one('load error', function () { if (--pending === 0) relayout($grid); });
      });
      if (pending) setTimeout(function () { relayout($grid); }, 1500); // 일부 이벤트가 누락돼도 한 번 더
    });
  });
})(jQuery);
