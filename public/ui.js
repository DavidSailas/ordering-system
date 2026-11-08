/* Cecilian Bites — shared UI behaviour: confirm dialogs, toasts, add-to-cart, account menu. */
(function () {
  'use strict';

  /* ---------- toast ---------- */
  var toastBox;
  window.uiToast = function (message, options) {
    options = options || {};
    if (!toastBox) {
      toastBox = document.createElement('div');
      toastBox.className = 'ui-toasts';
      toastBox.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastBox);
    }
    var el = document.createElement('div');
    el.className = 'ui-toast' + (options.type === 'error' ? ' ui-toast--error' : '');
    var text = document.createElement('span');
    text.textContent = message;
    el.appendChild(text);
    if (options.href) {
      var a = document.createElement('a');
      a.href = options.href;
      a.textContent = options.label || 'View';
      el.appendChild(a);
    }
    toastBox.appendChild(el);
    setTimeout(function () { el.classList.add('is-leaving'); setTimeout(function () { el.remove(); }, 250); }, options.duration || 3500);
  };

  /* ---------- confirm dialog ---------- */
  var dialog;
  function buildDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'ui-modal';
    dialog.innerHTML =
      '<form method="dialog" class="ui-modal-body">' +
      '<div class="ui-modal-icon" aria-hidden="true">!</div>' +
      '<h3 class="ui-modal-title"></h3><p class="ui-modal-text"></p>' +
      '<div class="ui-modal-actions">' +
      '<button value="cancel" class="ui-btn">Cancel</button>' +
      '<button value="ok" class="ui-btn ui-btn--danger-solid" autofocus></button>' +
      '</div></form>';
    document.body.appendChild(dialog);
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close('cancel'); });
  }

  function ask(data) {
    var message = data.confirm || 'Are you sure?';
    if (typeof HTMLDialogElement === 'undefined') { return Promise.resolve(window.confirm(message)); }
    if (!dialog) buildDialog();
    dialog.querySelector('.ui-modal-title').textContent = data.confirmTitle || 'Are you sure?';
    dialog.querySelector('.ui-modal-text').textContent = message;
    var ok = dialog.querySelector('[value="ok"]');
    ok.textContent = data.confirmOk || 'Yes, continue';
    return new Promise(function (resolve) {
      dialog.addEventListener('close', function onClose() {
        dialog.removeEventListener('close', onClose);
        resolve(dialog.returnValue === 'ok');
      });
      dialog.returnValue = 'cancel';
      dialog.showModal();
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM' || (form.getAttribute('method') || '').toLowerCase() === 'dialog') return;

    /* add to cart without leaving the page */
    if (form.hasAttribute('data-cart-add')) { e.preventDefault(); addToCart(form); return; }

    var sub = e.submitter;
    var source = sub && sub.dataset && sub.dataset.confirm ? sub : form;
    if (!source.dataset || !source.dataset.confirm || form.dataset.confirmed) return;

    e.preventDefault();
    ask(source.dataset).then(function (yes) {
      if (!yes) return;
      if (sub && sub.name) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = sub.name; hidden.value = sub.value;
        form.appendChild(hidden);
      }
      form.dataset.confirmed = '1';
      form.submit();
    });
  });

  /* ---------- add to cart ---------- */
  function addToCart(form) {
    var button = form.querySelector('button');
    var card = form.closest('.dish');
    var label = button ? button.textContent : '';
    if (button) { button.disabled = true; button.textContent = 'Adding…'; }

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
      .then(function (r) {
        if (!r.ok) {
          var errors = r.body && r.body.errors ? Object.values(r.body.errors)[0][0] : (r.body && r.body.message);
          throw new Error(errors || 'Could not add this item.');
        }
        window.uiToast(r.body.message, { href: form.dataset.cartUrl || '/cart', label: 'View cart' });
        if (button) { button.textContent = 'Added ✓'; }
        return refreshCartAreas();
      })
      .catch(function (err) {
        window.uiToast(err.message || 'Something went wrong. Please try again.', { type: 'error', duration: 5000 });
        if (button) { button.textContent = label; }
      })
      .then(function () {
        setTimeout(function () { if (button) { button.disabled = false; button.textContent = label; } }, 1100);
      });
  }

  /* re-read the current page and swap the cart-related blocks (sidebar count, top bar, "Your order" panel) */
  function refreshCartAreas() {
    return fetch(location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) { return res.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        ['.customer-sidebar nav', '.cart-link', '.customer-order', '.mobile-cartbar', '.mobile-tabs'].forEach(function (selector) {
          var fresh = doc.querySelector(selector);
          var current = document.querySelector(selector);
          if (fresh && current) { current.innerHTML = fresh.innerHTML; current.hidden = fresh.hidden; }
        });
      })
      .catch(function () { /* the toast already confirmed the add */ });
  }

  /* ---------- account menu ---------- */
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.ui-menu[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('details.ui-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
  });
})();
