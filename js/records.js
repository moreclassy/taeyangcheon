/* 시공 실적 표: php/gallery/records_api.php 에서 공개 실적을 받아 연도별로 그린다.
 * API 가 실패하거나 실적이 없으면 HTML 에 있는 안내 문구가 그대로 보인다. */
(function ($) {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function ym(m) { return m ? m.replace('-', '.') : '-'; }

  $(function () {
    var $wrap = $('#records');
    if (!$wrap.length) return;
    var $summary = $('#records-summary');
    var $filter = $('#records-filter');
    var $table = $('#records-table');
    var all = [], types = {}, filter = '';

    function renderTable() {
      var rows = all.filter(function (r) { return !filter || r.site_type === filter; });
      if (!rows.length) {
        $table.html('<div class="records-empty"><p class="mb-0">해당 유형의 공개 실적이 없습니다.</p></div>');
        return;
      }
      var html = '', year = null;
      rows.forEach(function (r) {
        var y = r.work_month ? r.work_month.slice(0, 4) + '년' : '시기 미기재';
        if (y !== year) {
          year = y;
          html += '<tr class="records-year"><th colspan="7">' + esc(y) + '</th></tr>';
        }
        var photo = r.thumb
          ? '<a class="popup popup-img" href="' + esc(r.large) + '" title="' + esc(r.site_name) + '"><img src="' + esc(r.thumb) + '" alt="' + esc(r.site_name) + ' 시공 사진" loading="lazy"></a>'
          : '<span class="text-muted">-</span>';
        html += '<tr>' +
          '<td data-label="시공 시기">' + esc(ym(r.work_month)) + '</td>' +
          '<td data-label="현장명" class="records-name">' + esc(r.site_name) + (r.location ? '<small>' + esc(r.location) + '</small>' : '') + '</td>' +
          '<td data-label="현장 유형">' + esc(r.type_label) + '</td>' +
          '<td data-label="공법">' + esc(r.method_label) + '</td>' +
          '<td data-label="규모">' + esc(r.scale || '-') + '</td>' +
          '<td data-label="발주처">' + esc(r.client || '-') + '</td>' +
          '<td data-label="사진" class="records-photo">' + photo + '</td>' +
          '</tr>';
      });
      $table.html(
        '<table class="records-table">' +
        '<thead><tr><th>시공 시기</th><th>현장명</th><th>현장 유형</th><th>공법</th><th>규모</th><th>발주처</th><th>사진</th></tr></thead>' +
        '<tbody>' + html + '</tbody></table>'
      );
    }

    function renderSummary() {
      var dated = all.filter(function (r) { return r.work_month; }); // API 가 시기 내림차순으로 준다
      var latest = dated.length ? ym(dated[0].work_month) : '-';
      var typeCount = {};
      all.forEach(function (r) { typeCount[r.site_type] = (typeCount[r.site_type] || 0) + 1; });
      $summary.html(
        '<div class="records-stat"><strong>' + all.length + '</strong><span>공개 시공 실적</span></div>' +
        '<div class="records-stat"><strong>' + Object.keys(typeCount).length + '</strong><span>현장 유형</span></div>' +
        '<div class="records-stat"><strong>' + esc(latest) + '</strong><span>최근 시공</span></div>'
      ).show();

      var chips = '<button type="button" class="is-checked" data-type="">전체 ' + all.length + '</button>';
      Object.keys(types).forEach(function (k) {
        if (!typeCount[k]) return;
        chips += '<button type="button" data-type="' + esc(k) + '">' + esc(types[k]) + ' ' + typeCount[k] + '</button>';
      });
      $filter.html(chips).show().on('click', 'button', function () {
        $filter.find('button').removeClass('is-checked');
        $(this).addClass('is-checked');
        filter = $(this).data('type') || '';
        renderTable();
      });
    }

    $.getJSON('php/gallery/records_api.php').done(function (res) {
      if (!res || !res.items || !res.items.length) return; // 안내 문구 유지
      all = res.items;
      types = res.types || {};
      renderSummary();
      renderTable();
    });
  });
})(jQuery);
