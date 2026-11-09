/* Cecilian Bites — dish photo adjuster.
 * Drag to reposition, slider / wheel / pinch to zoom, rotate, reset.
 * The framed 4:3 result (800 × 600) replaces the file in the form's file input,
 * so the server receives exactly what the admin saw. Without JS the plain
 * file input keeps working and the server centre-crops as before.
 */
(function () {
  'use strict';

  var OUT_W = 800, OUT_H = 600, MAX_ZOOM = 4;

  function init(root) {
    var input = root.querySelector('[data-pa-input]');
    var form = root.closest('form');
    if (!input || !form || !window.HTMLCanvasElement || !window.DataTransfer) return;

    var $ = function (name) { return root.querySelector('[data-pa-' + name + ']'); };
    var drop = $('drop'), stage = $('stage'), canvas = $('canvas'), ctx = canvas.getContext('2d');
    var zoomInput = $('zoom'), zoomLabel = $('zoom-label'), note = $('note'), current = $('current');
    var removeBox = form.querySelector('[name="remove_image"]');

    canvas.width = OUT_W; canvas.height = OUT_H;

    var source = null, sw = 0, sh = 0;      // image or canvas being framed
    var cx = 0, cy = 0, zoom = 1;           // crop centre (source px) and zoom (1 = fills frame)
    var pending = null, timer = null;

    function baseScale() { return Math.max(OUT_W / sw, OUT_H / sh); }
    function clampCentre() {
      var s = baseScale() * zoom, rw = OUT_W / s, rh = OUT_H / s;
      cx = Math.min(Math.max(cx, rw / 2), sw - rw / 2);
      cy = Math.min(Math.max(cy, rh / 2), sh - rh / 2);
    }

    function draw() {
      if (!source) return;
      clampCentre();
      var s = baseScale() * zoom, rw = OUT_W / s, rh = OUT_H / s;
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, OUT_W, OUT_H);
      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(source, cx - rw / 2, cy - rh / 2, rw, rh, 0, 0, OUT_W, OUT_H);
      zoomInput.value = zoom;
      zoomLabel.textContent = Math.round(zoom * 100) + '%';
      queueExport();
    }

    /* ---------- hand the framed result to the form ---------- */
    function exportNow() {
      return new Promise(function (resolve) {
        if (!source) return resolve();
        var done = function (blob) {
          if (blob) {
            var ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
            var dt = new DataTransfer();
            dt.items.add(new File([blob], 'dish.' + ext, { type: blob.type }));
            input.files = dt.files;
          }
          resolve();
        };
        canvas.toBlob(function (webp) {
          if (webp && webp.type === 'image/webp') return done(webp);
          canvas.toBlob(done, 'image/jpeg', 0.88);
        }, 'image/webp', 0.85);
      });
    }
    function queueExport() {
      clearTimeout(timer);
      pending = new Promise(function (resolve) {
        timer = setTimeout(function () { exportNow().then(function () { pending = null; resolve(); }); }, 120);
      });
    }

    form.addEventListener('submit', function (e) {
      if (!source || !pending || form.dataset.paReady) return;
      e.preventDefault();
      var submitter = e.submitter;
      clearTimeout(timer);
      exportNow().then(function () {
        form.dataset.paReady = '1';
        form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
      });
    });

    /* ---------- loading a file ---------- */
    function setNote(text, warn) {
      note.textContent = text || '';
      note.hidden = !text;
      note.classList.toggle('is-warn', !!warn);
    }

    function load(file) {
      if (!file) return;
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
        input.value = '';
        return setNote('Please choose a JPG, PNG or WebP photo.', true);
      }
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        source = img; sw = img.naturalWidth; sh = img.naturalHeight;
        reset(true);
        root.classList.add('has-photo');
        if (current) current.hidden = true;
        if (removeBox) removeBox.checked = false;
        setNote(sw < 400 || sh < 300
          ? 'This photo is small (' + sw + ' × ' + sh + ' px) and may look soft. A larger photo is sharper.'
          : '', sw < 400 || sh < 300);
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        input.value = '';
        setNote('That file could not be read. Try a different photo.', true);
      };
      img.src = url;
    }

    function reset(silent) {
      if (!source) return;
      zoom = 1; cx = sw / 2; cy = sh / 2;
      draw();
    }

    function rotate() {
      if (!source) return;
      var c = document.createElement('canvas');
      c.width = sh; c.height = sw;
      var g = c.getContext('2d');
      g.translate(c.width, 0); g.rotate(Math.PI / 2);
      g.drawImage(source, 0, 0);
      source = c; var t = sw; sw = sh; sh = t;
      zoom = 1; cx = sw / 2; cy = sh / 2;
      draw();
    }

    input.addEventListener('change', function () {
      // Setting input.files from code does not fire "change", so this is always a real pick.
      if (input.files && input.files[0]) load(input.files[0]);
    });

    /* ---------- drag-and-drop onto the drop zone ---------- */
    ['dragenter', 'dragover'].forEach(function (t) {
      drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) {
      var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) load(f);
    });

    /* ---------- pan (drag) + pinch zoom ---------- */
    var pointers = {}, lastDist = 0;
    function count() { return Object.keys(pointers).length; }
    function ratio() { return OUT_W / canvas.getBoundingClientRect().width; }

    canvas.addEventListener('pointerdown', function (e) {
      if (!source) return;
      canvas.setPointerCapture(e.pointerId);
      pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
      stage.classList.add('is-dragging');
      lastDist = 0;
    });
    canvas.addEventListener('pointermove', function (e) {
      var p = pointers[e.pointerId];
      if (!p || !source) return;
      if (count() === 1) {
        var s = baseScale() * zoom, k = ratio() / s;
        cx -= (e.clientX - p.x) * k;
        cy -= (e.clientY - p.y) * k;
        p.x = e.clientX; p.y = e.clientY;
        draw();
      } else if (count() === 2) {
        p.x = e.clientX; p.y = e.clientY;
        var ids = Object.keys(pointers), a = pointers[ids[0]], b = pointers[ids[1]];
        var d = Math.hypot(a.x - b.x, a.y - b.y);
        if (lastDist) { zoom = Math.min(MAX_ZOOM, Math.max(1, zoom * d / lastDist)); draw(); }
        lastDist = d;
      }
    });
    function endPointer(e) {
      delete pointers[e.pointerId]; lastDist = 0;
      if (!count()) stage.classList.remove('is-dragging');
    }
    canvas.addEventListener('pointerup', endPointer);
    canvas.addEventListener('pointercancel', endPointer);

    canvas.addEventListener('wheel', function (e) {
      if (!source) return;
      e.preventDefault();
      zoom = Math.min(MAX_ZOOM, Math.max(1, zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08)));
      draw();
    }, { passive: false });

    /* ---------- keyboard: arrows move, + / − zoom ---------- */
    canvas.addEventListener('keydown', function (e) {
      if (!source) return;
      var step = (e.shiftKey ? 40 : 12) / (baseScale() * zoom), handled = true;
      if (e.key === 'ArrowLeft') cx -= step;
      else if (e.key === 'ArrowRight') cx += step;
      else if (e.key === 'ArrowUp') cy -= step;
      else if (e.key === 'ArrowDown') cy += step;
      else if (e.key === '+' || e.key === '=') zoom = Math.min(MAX_ZOOM, zoom + 0.1);
      else if (e.key === '-') zoom = Math.max(1, zoom - 0.1);
      else handled = false;
      if (handled) { e.preventDefault(); draw(); }
    });

    /* ---------- controls ---------- */
    zoomInput.addEventListener('input', function () { zoom = parseFloat(zoomInput.value) || 1; draw(); });
    $('zoom-in').addEventListener('click', function () { zoom = Math.min(MAX_ZOOM, zoom + 0.25); draw(); });
    $('zoom-out').addEventListener('click', function () { zoom = Math.max(1, zoom - 0.25); draw(); });
    $('rotate').addEventListener('click', rotate);
    $('reset').addEventListener('click', function () { reset(); });
    Array.prototype.forEach.call(root.querySelectorAll('[data-pa-change]'), function (b) {
      b.addEventListener('click', function () { input.click(); });
    });
    $('discard').addEventListener('click', function () {
      source = null; input.value = '';
      root.classList.remove('has-photo');
      if (current) current.hidden = false;
      setNote('');
    });

    if (removeBox) {
      removeBox.addEventListener('change', function () {
        if (removeBox.checked && source) $('discard').click();
        if (current) current.classList.toggle('is-removed', removeBox.checked);
      });
    }
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-photo-adjuster]'), init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
