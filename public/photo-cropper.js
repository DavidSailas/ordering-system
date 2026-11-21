/* Cecilian Bites — dish photo editor.
   Pick a photo → a 4:3 frame opens where you drag to reposition and zoom (mouse wheel, slider,
   pinch on phones, arrow keys). "Use this photo" crops it exactly as shown, shrinks it to a web-friendly
   1200 × 900 image and puts it in the form's file field. Plain JavaScript, no libraries.
   If anything is unsupported the normal file upload still works (server crops from the centre). */
(function () {
  'use strict';

  var OUT_W = 1200, OUT_H = 900, MAX_ZOOM = 4;
  var canEdit = (function () {
    try { new DataTransfer(); return !!(window.HTMLCanvasElement && window.HTMLDialogElement && window.URL && URL.createObjectURL); }
    catch (e) { return false; }
  })();
  if (!canEdit) { return; }

  var dialog, stage, imgEl, slider, warn;
  var nat = { w: 0, h: 0 };
  var st = { s: 1, s0: 1, x: 0, y: 0, fw: 0, fh: 0 };
  var session = null;      // { url, file, done, cancel }
  var pts = {}, lastDist = null;

  function clamp(v, a, b) { return Math.min(b, Math.max(a, v)); }

  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'pc-dialog';
    dialog.innerHTML =
      '<div class="pc-sheet">' +
        '<header class="pc-head"><div><h3>Adjust photo</h3>' +
        '<p>Drag to move the photo. Use the slider or pinch to zoom.</p></div>' +
        '<button type="button" class="pc-x" data-act="cancel" aria-label="Close">&times;</button></header>' +
        '<div class="pc-stage" tabindex="0" role="application" aria-label="Photo editor. Arrow keys move the photo, plus and minus zoom.">' +
          '<img class="pc-img" alt="" draggable="false"><div class="pc-grid" aria-hidden="true"></div>' +
        '</div>' +
        '<p class="pc-warn" hidden></p>' +
        '<div class="pc-zoom">' +
          '<button type="button" class="pc-round" data-act="out" aria-label="Zoom out">&minus;</button>' +
          '<input type="range" min="0" max="100" value="0" step="1" aria-label="Zoom">' +
          '<button type="button" class="pc-round" data-act="in" aria-label="Zoom in">+</button>' +
        '</div>' +
        '<footer class="pc-foot"><button type="button" class="pc-btn pc-btn--ghost" data-act="reset">Reset</button>' +
        '<span class="pc-spacer"></span>' +
        '<button type="button" class="pc-btn" data-act="cancel">Cancel</button>' +
        '<button type="button" class="pc-btn pc-btn--primary" data-act="save">Use this photo</button></footer>' +
      '</div>';
    document.body.appendChild(dialog);

    stage = dialog.querySelector('.pc-stage');
    imgEl = dialog.querySelector('.pc-img');
    slider = dialog.querySelector('input[type=range]');
    warn = dialog.querySelector('.pc-warn');

    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) { finish(false); return; }
      var act = e.target.closest && e.target.closest('[data-act]');
      if (!act) { return; }
      var a = act.getAttribute('data-act');
      if (a === 'cancel') { finish(false); }
      else if (a === 'save') { save(); }
      else if (a === 'reset') { layout(); }
      else if (a === 'in') { zoomAt(st.fw / 2, st.fh / 2, 1.2); }
      else if (a === 'out') { zoomAt(st.fw / 2, st.fh / 2, 1 / 1.2); }
    });
    dialog.addEventListener('cancel', function (e) { e.preventDefault(); finish(false); });   // Esc key

    slider.addEventListener('input', function () {
      var target = st.s0 * (1 + (slider.value / 100) * (MAX_ZOOM - 1));
      zoomAt(st.fw / 2, st.fh / 2, target / st.s);
    });

    // drag + pinch
    stage.addEventListener('pointerdown', function (e) {
      stage.setPointerCapture(e.pointerId);
      pts[e.pointerId] = { x: e.clientX, y: e.clientY };
      lastDist = null;
      stage.classList.add('is-drag');
    });
    stage.addEventListener('pointermove', function (e) {
      var prev = pts[e.pointerId];
      if (!prev) { return; }
      var ids = Object.keys(pts);
      if (ids.length === 1) {
        st.x += e.clientX - prev.x; st.y += e.clientY - prev.y;
        pts[e.pointerId] = { x: e.clientX, y: e.clientY };
        render();
      } else if (ids.length === 2) {
        pts[e.pointerId] = { x: e.clientX, y: e.clientY };
        var a = pts[ids[0]], b = pts[ids[1]];
        var d = Math.hypot(a.x - b.x, a.y - b.y);
        var box = stage.getBoundingClientRect();
        if (lastDist) { zoomAt((a.x + b.x) / 2 - box.left, (a.y + b.y) / 2 - box.top, d / lastDist); }
        lastDist = d;
      }
    });
    function release(e) { delete pts[e.pointerId]; lastDist = null; if (!Object.keys(pts).length) { stage.classList.remove('is-drag'); } }
    stage.addEventListener('pointerup', release);
    stage.addEventListener('pointercancel', release);
    stage.addEventListener('wheel', function (e) {
      e.preventDefault();
      var box = stage.getBoundingClientRect();
      zoomAt(e.clientX - box.left, e.clientY - box.top, Math.exp(-e.deltaY * 0.0015));
    }, { passive: false });
    stage.addEventListener('keydown', function (e) {
      var step = e.shiftKey ? 40 : 12, handled = true;
      if (e.key === 'ArrowLeft') { st.x += step; }
      else if (e.key === 'ArrowRight') { st.x -= step; }
      else if (e.key === 'ArrowUp') { st.y += step; }
      else if (e.key === 'ArrowDown') { st.y -= step; }
      else if (e.key === '+' || e.key === '=') { zoomAt(st.fw / 2, st.fh / 2, 1.1); handled = false; }
      else if (e.key === '-' || e.key === '_') { zoomAt(st.fw / 2, st.fh / 2, 1 / 1.1); handled = false; }
      else { handled = false; }
      if (handled) { e.preventDefault(); render(); }
    });
    window.addEventListener('resize', function () { if (dialog.open) { layout(); } });
  }

  // fit the photo so it just covers the frame, centred
  function layout() {
    st.fw = stage.clientWidth; st.fh = stage.clientHeight;
    st.s0 = Math.max(st.fw / nat.w, st.fh / nat.h);
    st.s = st.s0;
    st.x = (st.fw - nat.w * st.s) / 2;
    st.y = (st.fh - nat.h * st.s) / 2;
    render();
  }

  function render() {
    st.x = clamp(st.x, st.fw - nat.w * st.s, 0);
    st.y = clamp(st.y, st.fh - nat.h * st.s, 0);
    imgEl.style.width = nat.w * st.s + 'px';
    imgEl.style.height = nat.h * st.s + 'px';
    imgEl.style.transform = 'translate(' + st.x + 'px,' + st.y + 'px)';
    slider.value = Math.round(((st.s / st.s0) - 1) / (MAX_ZOOM - 1) * 100);
  }

  function zoomAt(px, py, factor) {
    var ns = clamp(st.s * factor, st.s0, st.s0 * MAX_ZOOM);
    var ratio = ns / st.s;
    st.x = px - (px - st.x) * ratio;
    st.y = py - (py - st.y) * ratio;
    st.s = ns;
    render();
  }

  function open(file, done, cancel) {
    if (!dialog) { build(); }
    if (session) { URL.revokeObjectURL(session.url); }
    session = { url: URL.createObjectURL(file), file: file, done: done, cancel: cancel };
    imgEl.onload = function () {
      nat.w = imgEl.naturalWidth; nat.h = imgEl.naturalHeight;
      if (nat.w < 800 || nat.h < 600) {
        warn.hidden = false;
        warn.textContent = 'This photo is small (' + nat.w + ' × ' + nat.h + ' px) and may look blurry. A larger photo will look sharper.';
      } else { warn.hidden = true; }
      if (!dialog.open) { dialog.showModal(); }
      requestAnimationFrame(function () { layout(); stage.focus({ preventScroll: true }); });
    };
    imgEl.onerror = function () {
      var s = session; session = null;
      if (dialog.open) { dialog.close(); }
      window.alert('That file could not be opened as a photo. Please choose a JPG, PNG or WebP image.');
      if (s && s.cancel) { s.cancel(); }
    };
    imgEl.style.width = imgEl.style.height = ''; imgEl.style.transform = '';
    imgEl.src = session.url;
  }

  function finish(ok, result) {
    var s = session;
    if (dialog.open) { dialog.close(); }
    pts = {}; lastDist = null;
    if (!s) { return; }
    if (ok) { s.done(result); } else if (s.cancel) { s.cancel(); }
  }

  function save() {
    var s = session;
    if (!s) { return; }
    var canvas = document.createElement('canvas');
    canvas.width = OUT_W; canvas.height = OUT_H;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, OUT_W, OUT_H);
    ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(imgEl, -st.x / st.s, -st.y / st.s, st.fw / st.s, st.fh / st.s, 0, 0, OUT_W, OUT_H);

    function done(blob, ext) {
      var base = (s.file.name || 'dish').replace(/\.[^.]+$/, '') || 'dish';
      var file = new File([blob], base + '.' + ext, { type: blob.type, lastModified: Date.now() });
      finish(true, { file: file, url: URL.createObjectURL(blob) });
    }
    canvas.toBlob(function (blob) {
      if (blob && blob.type === 'image/webp') { done(blob, 'webp'); return; }
      canvas.toBlob(function (jpg) { if (jpg) { done(jpg, 'jpg'); } }, 'image/jpeg', 0.88);
    }, 'image/webp', 0.86);
  }

  /* ---------- the form field ---------- */
  function initField(field) {
    var input = field.querySelector('[data-photo-input]');
    var preview = field.querySelector('[data-photo-preview]');
    var adjust = field.querySelector('[data-photo-adjust]');
    var pick = field.querySelector('[data-photo-pick]');
    var status = field.querySelector('[data-photo-status]');
    var removeBox = field.parentNode.querySelector('input[name=remove_image]');
    var original = null, applied = false;
    if (!input) { return; }

    function edit(file) {
      open(file, function (r) {
        var dt = new DataTransfer(); dt.items.add(r.file); input.files = dt.files;
        applied = true;
        if (preview) {
          preview.innerHTML = '';
          var im = document.createElement('img');
          im.src = r.url; im.alt = 'New photo preview'; im.width = 800; im.height = 600;
          preview.appendChild(im);
        }
        if (adjust) { adjust.hidden = false; }
        if (pick) { pick.textContent = 'Choose a different photo'; }
        if (status) { status.hidden = false; status.textContent = 'New photo ready. It is saved when you press Save.'; }
        if (removeBox) { removeBox.checked = false; }
      }, function () {
        if (!applied) { input.value = ''; }
      });
    }

    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) { return; }
      if (!/^image\//.test(f.type)) { window.alert('Please choose an image file (JPG, PNG or WebP).'); input.value = ''; return; }
      original = f;
      edit(f);
    });
    if (adjust) { adjust.addEventListener('click', function () { if (original) { edit(original); } }); }

    // drag a photo straight onto the preview
    ['dragenter', 'dragover'].forEach(function (n) { field.addEventListener(n, function (e) { e.preventDefault(); field.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (n) { field.addEventListener(n, function () { field.classList.remove('is-over'); }); });
    field.addEventListener('drop', function (e) {
      e.preventDefault();
      var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!f || !/^image\//.test(f.type)) { return; }
      original = f; edit(f);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    [].forEach.call(document.querySelectorAll('[data-photo-field]'), initField);
  });
})();
