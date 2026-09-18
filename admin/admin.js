/* 갤러리 · 시공 실적 관리자 UI */
(function () {
  'use strict';
  var body = document.body;
  var csrf = body.dataset.csrf;
  var categories = JSON.parse(body.dataset.categories || '{}');
  var catKeys = Object.keys(categories);
  var API = 'api.php';
  var MAX_SIDE = 1600;   // 폰에서 업로드 전 축소할 긴 변 (서버에서도 다시 처리함)

  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

  var toastTimer;
  function toast(msg, ms) {
    var t = $('#toast'); t.textContent = msg; t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.hidden = true; }, ms || 2200);
  }

  function api(action, data, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'POST', headers: { 'X-CSRF-Token': csrf }, credentials: 'same-origin' };
    var url = API;
    if (init.method === 'GET') {
      url += '?action=' + encodeURIComponent(action);
    } else {
      var fd = data instanceof FormData ? data : new FormData();
      if (!(data instanceof FormData) && data) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      fd.append('action', action);
      init.body = fd;
    }
    return fetch(url, init).then(function (r) {
      return r.json().catch(function () { return { error: '서버 응답 오류 (' + r.status + ')' }; })
        .then(function (j) {
          if (!r.ok || j.error) {
            if (j.code === 'auth') { location.reload(); }
            throw new Error(j.error || ('오류 ' + r.status));
          }
          return j;
        });
    });
  }

  // ---------- 구분 칩 ----------
  function renderChips(container, selected, onChange, includeAll) {
    container.innerHTML = '';
    var keys = includeAll ? [''].concat(catKeys) : catKeys;
    keys.forEach(function (k) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'chip' + (k === selected ? ' on' : ''); b.dataset.value = k;
      b.textContent = k === '' ? '전체' : categories[k].label;
      b.addEventListener('click', function () {
        $$('.chip', container).forEach(function (c) { c.classList.toggle('on', c === b); });
        onChange(k);
      });
      container.appendChild(b);
    });
  }

  // ---------- 업로드 ----------
  var selected = [];           // {file, url, el}
  var uploadCat = catKeys[0] || '';
  var filesInput = $('#files');
  var previews = $('#previews');
  var uploadBtn = $('#upload');
  var uploadStatus = $('#upload-status');

  renderChips($('#cat-chips'), uploadCat, function (k) { uploadCat = k; });

  filesInput.addEventListener('change', function () {
    Array.prototype.forEach.call(filesInput.files, function (f) {
      if (!/^image\//.test(f.type) && !/\.(heic|heif|jpe?g|png|webp)$/i.test(f.name)) return;
      var url = URL.createObjectURL(f);
      var el = document.createElement('div');
      el.className = 'preview';
      el.innerHTML = '<img alt=""><button type="button" class="rm" aria-label="제외">×</button><span class="st"></span>';
      $('img', el).src = url;
      var item = { file: f, url: url, el: el };
      $('.rm', el).addEventListener('click', function () {
        selected = selected.filter(function (s) { return s !== item; });
        URL.revokeObjectURL(url); el.remove(); refreshUploadBtn();
      });
      previews.appendChild(el);
      selected.push(item);
    });
    filesInput.value = '';
    refreshUploadBtn();
  });

  function refreshUploadBtn() {
    uploadBtn.disabled = selected.length === 0;
    uploadBtn.textContent = selected.length ? '올리기 (' + selected.length + '장)' : '올리기';
  }

  // 브라우저에서 먼저 축소 (전송량 절감). 실패하면 원본 그대로 보낸다.
  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return resolve(file);
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight;
        var scale = Math.min(1, MAX_SIDE / Math.max(w, h));
        if (scale === 1 && file.size < 1.5 * 1024 * 1024) return resolve(file);
        var c = document.createElement('canvas');
        c.width = Math.round(w * scale); c.height = Math.round(h * scale);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
          if (!blob) return resolve(file);
          resolve(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.86);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  uploadBtn.addEventListener('click', function () {
    if (!selected.length) return;
    if (!uploadCat) { toast('구분을 선택해주세요'); return; }
    var title = $('#title').value.trim();
    var queue = selected.slice();
    var total = queue.length, done = 0, failed = 0;
    uploadBtn.disabled = true; filesInput.disabled = true;
    uploadStatus.className = 'status';

    function next() {
      if (!queue.length) {
        uploadStatus.className = 'status ' + (failed ? 'err' : 'ok');
        uploadStatus.textContent = failed ? (done + '장 완료, ' + failed + '장 실패') : (done + '장 업로드 완료');
        selected = selected.filter(function (s) { return !s.el.classList.contains('done'); });
        if (!failed) { $('#title').value = ''; }
        filesInput.disabled = false; refreshUploadBtn();
        loadList();
        return;
      }
      var item = queue.shift();
      var idx = total - queue.length;
      uploadStatus.textContent = idx + ' / ' + total + ' 올리는 중…';
      $('.st', item.el).textContent = '올리는 중';
      shrink(item.file).then(function (f) {
        var fd = new FormData();
        fd.append('file', f, f.name);
        fd.append('category', uploadCat);
        fd.append('title', title);
        return api('upload', fd);
      }).then(function () {
        done++; item.el.classList.add('done'); $('.st', item.el).textContent = '완료';
        setTimeout(function () { item.el.remove(); URL.revokeObjectURL(item.url); }, 800);
      }).catch(function (e) {
        failed++; item.el.classList.add('fail'); $('.st', item.el).textContent = '실패';
        toast(e.message, 4000);
      }).then(next);
    }
    next();
  });

  // ---------- 목록 ----------
  var items = [];
  var listFilter = '';
  var listEl = $('#list');
  var tpl = $('#photo-tpl');

  renderChips($('#list-filter'), '', function (k) { listFilter = k; renderList(); }, true);

  function loadList() {
    return api('list', null, { method: 'GET' }).then(function (j) {
      items = j.items || [];
      renderList();
    }).catch(function (e) { listEl.innerHTML = '<p class="empty">' + esc(e.message) + '</p>'; });
  }

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function renderList() {
    var shown = items.filter(function (it) { return !listFilter || it.category === listFilter; });
    $('#count').textContent = listFilter ? shown.length + ' / ' + items.length : items.length;
    listEl.innerHTML = '';
    if (!shown.length) { listEl.innerHTML = '<p class="empty">사진이 없습니다.</p>'; return; }
    shown.forEach(function (it) { listEl.appendChild(renderPhoto(it)); });
  }

  function renderPhoto(it) {
    var node = tpl.content.firstElementChild.cloneNode(true);
    node.dataset.id = it.id;
    var a = $('.thumb', node); a.href = '../' + it.large;
    var img = $('img', a); img.src = '../' + it.thumb; img.alt = it.title;
    $('.cat', node).textContent = it.label;
    $('.ptitle', node).textContent = it.title;

    var editor = $('.editor', node);
    var editCat = it.category;
    $('.edit', node).addEventListener('click', function () {
      editCat = it.category;
      renderChips($('.edit-cats', editor), editCat, function (k) { editCat = k; });
      editor.title.value = it.title;
      editor.hidden = false; editor.title.focus();
    });
    $('.cancel', editor).addEventListener('click', function () { editor.hidden = true; });
    editor.addEventListener('submit', function (e) {
      e.preventDefault();
      busy(node, api('update', { id: it.id, title: editor.title.value, category: editCat }).then(function (j) {
        Object.assign(it, j.item); renderList(); toast('저장했습니다');
      }));
    });
    $('.top', node).addEventListener('click', function () {
      busy(node, api('top', { id: it.id }).then(function () { toast('맨 위로 옮겼습니다'); return loadList(); }));
    });
    $('.del', node).addEventListener('click', function () {
      if (!confirm('이 사진을 삭제할까요?\n' + (it.title || '(제목 없음)'))) return;
      busy(node, api('delete', { id: it.id }).then(function () {
        items = items.filter(function (x) { return x.id !== it.id; }); renderList(); toast('삭제했습니다');
      }));
    });
    return node;
  }

  function busy(node, p) {
    node.classList.add('busy');
    return p.catch(function (e) { toast(e.message, 4000); }).then(function () { node.classList.remove('busy'); });
  }

  // ---------- 비밀번호 / 로그아웃 ----------
  $('#pw-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target, st = $('#pw-status');
    if (f.new.value !== f.new2.value) { st.className = 'status err'; st.textContent = '새 비밀번호가 서로 다릅니다.'; return; }
    api('password', { current: f.current.value, new: f.new.value }).then(function (j) {
      csrf = j.csrf || csrf; f.reset();
      st.className = 'status ok'; st.textContent = '비밀번호를 변경했습니다.';
    }).catch(function (err) { st.className = 'status err'; st.textContent = err.message; });
  });

  $('#logout').addEventListener('click', function () {
    api('logout').then(function () { location.reload(); }).catch(function () { location.reload(); });
  });

  // ---------- 시공 실적 ----------
  var recTypes = JSON.parse(body.dataset.recordTypes || '{}');
  var recMethods = JSON.parse(body.dataset.recordMethods || '{}');
  var records = [];
  var recFilter = '';
  var recForm = $('#rec-form'), recList = $('#rec-list'), recTpl = $('#record-tpl'), recStatus = $('#rec-status');

  function fillSelect(sel, map, selected) {
    sel.innerHTML = '';
    Object.keys(map).forEach(function (k) {
      var o = document.createElement('option'); o.value = k; o.textContent = map[k]; o.selected = k === selected; sel.appendChild(o);
    });
  }
  function fillPhotoSelect(selectedId) {
    var sel = recForm.photo_id;
    sel.innerHTML = '<option value="0">(없음)</option>';
    items.forEach(function (it) {
      var o = document.createElement('option'); o.value = it.id;
      o.textContent = (it.title || '(제목 없음)') + ' · ' + it.label;
      o.selected = it.id === selectedId; sel.appendChild(o);
    });
  }
  function ym(m) { return m ? m.replace('-', '.') : '시기 미정'; }

  (function () {
    var c = $('#rec-filter');
    [['', '전체'], ['pub', '공개'], ['draft', '비공개']].forEach(function (pair) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'chip' + (pair[0] === '' ? ' on' : ''); b.textContent = pair[1];
      b.addEventListener('click', function () {
        $$('.chip', c).forEach(function (x) { x.classList.toggle('on', x === b); });
        recFilter = pair[0]; renderRecords();
      });
      c.appendChild(b);
    });
  })();

  function openRecForm(rec) {
    rec = rec || {};
    recForm.id.value = rec.id || 0;
    recForm.site_name.value = rec.site_name || '';
    recForm.work_month.value = rec.work_month || '';
    recForm.location.value = rec.location || '';
    recForm.client.value = rec.client || '';
    fillSelect(recForm.site_type, recTypes, rec.site_type || 'other');
    fillSelect(recForm.method, recMethods, rec.method || 'long');
    recForm.scale.value = rec.scale || '';
    recForm.note.value = rec.note || '';
    fillPhotoSelect(rec.photo_id || 0);
    recForm.is_public.checked = rec.id ? !!rec.is_public : true;
    recStatus.className = 'status'; recStatus.textContent = '';
    recForm.hidden = false;
    recForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    recForm.site_name.focus();
  }
  $('#rec-add').addEventListener('click', function () { openRecForm(null); });
  $('#rec-cancel').addEventListener('click', function () { recForm.hidden = true; });

  recForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(recForm);
    fd.set('is_public', recForm.is_public.checked ? '1' : '0');
    recStatus.className = 'status'; recStatus.textContent = '저장 중…';
    api('record_save', fd).then(function () {
      recForm.hidden = true; toast('저장했습니다'); return loadRecords();
    }).catch(function (err) { recStatus.className = 'status err'; recStatus.textContent = err.message; });
  });

  function loadRecords() {
    return api('records', null, { method: 'GET' }).then(function (j) {
      records = j.items || []; renderRecords();
    }).catch(function (e) { recList.innerHTML = '<p class="empty">' + esc(e.message) + '</p>'; });
  }

  function renderRecords() {
    var shown = records.filter(function (r) { return !recFilter || (recFilter === 'pub' ? r.is_public : !r.is_public); });
    var pub = records.filter(function (r) { return r.is_public; }).length;
    $('#rec-count').textContent = '공개 ' + pub + ' / 전체 ' + records.length;
    recList.innerHTML = '';
    if (!shown.length) { recList.innerHTML = '<p class="empty">실적이 없습니다.</p>'; return; }
    shown.forEach(function (r) { recList.appendChild(renderRecord(r)); });
  }

  function renderRecord(r) {
    var node = recTpl.content.firstElementChild.cloneNode(true);
    node.dataset.id = r.id;
    node.classList.toggle('draft', !r.is_public);
    var a = $('.thumb', node);
    if (r.thumb) { a.href = '../' + r.large; $('img', a).src = '../' + r.thumb; }
    else { a.classList.add('none'); a.removeAttribute('href'); }
    $('.when', node).textContent = ym(r.work_month);
    $('.pub', node).textContent = r.is_public ? '공개' : '비공개';
    $('.rname', node).textContent = r.site_name;
    $('.rsub', node).textContent = [r.location, r.client, r.type_label, r.method_label, r.scale].filter(Boolean).join(' · ');
    $('.toggle', node).textContent = r.is_public ? '비공개로' : '공개하기';
    $('.edit', node).addEventListener('click', function () { openRecForm(r); });
    $('.toggle', node).addEventListener('click', function () {
      busy(node, api('record_public', { id: r.id, is_public: r.is_public ? '0' : '1' }).then(function () {
        toast(r.is_public ? '비공개로 바꿨습니다' : '공개했습니다'); return loadRecords();
      }));
    });
    $('.del', node).addEventListener('click', function () {
      if (!confirm('이 실적을 삭제할까요?\n' + r.site_name)) return;
      busy(node, api('record_delete', { id: r.id }).then(function () {
        records = records.filter(function (x) { return x.id !== r.id; }); renderRecords(); toast('삭제했습니다');
      }));
    });
    return node;
  }

  loadList().then(loadRecords);
})();
