// LaundryTrack — small progressive enhancements. Every page also works without this file.
(function () {
  'use strict';
  var root = document.documentElement;
  var $ = function (sel, el) { return (el || document).querySelector(sel); };
  var $$ = function (sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
  };

  // Sidebar: collapse on desktop (remembered), drawer on phones.
  $$('[data-collapse]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = root.classList.toggle('side-collapsed');
      store.set('lt-side', on ? '1' : '0');
    });
  });
  var lastFocus = null;
  function openNav() {
    lastFocus = document.activeElement;
    root.classList.add('nav-open');
    var first = $('#sidebar .nav-link');
    if (first) setTimeout(function () { first.focus(); }, 50);
  }
  function closeNav() {
    if (!root.classList.contains('nav-open')) return;
    root.classList.remove('nav-open');
    if (lastFocus) lastFocus.focus();
  }
  $$('[data-nav-open]').forEach(function (b) { b.addEventListener('click', openNav); });
  $$('[data-nav-close]').forEach(function (b) { b.addEventListener('click', closeNav); });

  // Account menu
  $$('[data-menu]').forEach(function (btn) {
    var pop = btn.nextElementSibling;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = pop.hidden;
      pop.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', function (e) {
      if (!pop.hidden && !pop.contains(e.target)) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeNav();
    $$('[data-menu]').forEach(function (btn) {
      if (!btn.nextElementSibling.hidden) { btn.nextElementSibling.hidden = true; btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
    });
  });

  // Whole table rows open their record; links, buttons and forms inside keep their own behaviour.
  $$('[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, form, label')) return;
      if (window.getSelection && String(window.getSelection())) return;
      window.location.href = row.getAttribute('data-href');
    });
  });

  // Show/hide sections that depend on a radio choice: data-show-when="name=value"
  var conditionals = $$('[data-show-when]');
  function syncConditionals() {
    conditionals.forEach(function (el) {
      var parts = el.getAttribute('data-show-when').split('=');
      var form = el.closest('form') || document;
      var checked = form.querySelector('input[name="' + parts[0] + '"]:checked');
      el.hidden = !checked || checked.value !== parts[1];
    });
  }
  if (conditionals.length) {
    document.addEventListener('change', function (e) { if (e.target.type === 'radio') syncConditionals(); });
    syncConditionals();
  }

  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var target = document.getElementById(b.getAttribute('data-toggle'));
      if (!target) return;
      target.hidden = !target.hidden;
      b.setAttribute('aria-expanded', String(!target.hidden));
      if (!target.hidden) { var f = target.querySelector('input:not([type=hidden]), select'); if (f) f.focus(); }
    });
  });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  $$('select[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.requestSubmit ? s.form.requestSubmit() : s.form.submit(); }); });

  // Inline validation: checks visible fields, explains the problem next to the field.
  function messageFor(el) {
    var v = el.validity;
    if (el.hasAttribute('data-match')) {
      var other = document.getElementById(el.getAttribute('data-match'));
      if (other && other.value !== el.value) return "The passwords don't match.";
    }
    if (v.valueMissing) return 'This is required.';
    if (v.typeMismatch && el.type === 'email') return 'Enter a valid email.';
    if (v.patternMismatch && el.name === 'last4') return 'Enter exactly 4 digits.';
    if (v.tooShort) return 'Use at least ' + el.minLength + ' characters.';
    if (v.rangeUnderflow) return 'Enter ' + el.min + ' or more.';
    if (v.rangeOverflow) return 'Enter ' + el.max + ' or less.';
    if (v.badInput || v.stepMismatch) return 'Enter a valid number.';
    return el.validationMessage || '';
  }
  function showError(el, msg) {
    var field = el.closest('.field') || el.parentNode;
    var err = field.querySelector('.field-error.js');
    var server = field.querySelector('.field-error:not(.js)');
    if (server) server.remove();
    field.classList.toggle('invalid', !!msg);
    el.setAttribute('aria-invalid', msg ? 'true' : 'false');
    if (!msg) { if (err) err.remove(); return; }
    if (!err) {
      err = document.createElement('small');
      err.className = 'field-error js';
      err.id = (el.id || el.name) + '-err';
      field.appendChild(err);
      el.setAttribute('aria-describedby', err.id);
    }
    err.textContent = msg;
  }
  function visible(el) { return !el.closest('[hidden]') && el.type !== 'hidden'; }
  $$('form[data-validate]').forEach(function (form) {
    var fields = $$('input, select, textarea', form);
    fields.forEach(function (el) {
      el.addEventListener('blur', function () { if (el.value !== '' && visible(el)) showError(el, messageFor(el)); });
      el.addEventListener('input', function () { if (el.closest('.invalid')) showError(el, messageFor(el)); });
    });
    $$('.field-error', form).forEach(function (err) {
      var f = err.closest('.field');
      if (f) f.classList.add('invalid');
    });
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var first = null;
      fields.forEach(function (el) {
        if (!visible(el)) return;
        var needed = el.getAttribute('data-required-when');
        var msg = messageFor(el);
        if (!msg && needed && el.value.trim() === '') msg = 'This is required.';
        if (msg || el.closest('.invalid')) showError(el, msg);
        if (msg && !first) first = el;
      });
      if (form.hasAttribute('data-new-order') && !newOrderCustomerOk(form)) {
        first = first || $('[data-customer-search]', form);
      }
      if (first) { e.preventDefault(); first.focus(); return; }
      busy(form);
    });
  });
  function busy(form) {
    var b = form.querySelector('button[type=submit], button:not([type])');
    if (b) setTimeout(function () { b.classList.add('is-busy'); b.setAttribute('aria-busy', 'true'); }, 0);
  }
  // Confirm before destructive or unusual actions; other forms just guard double submits.
  $$('form').forEach(function (form) {
    if (form.hasAttribute('data-validate')) return;
    form.addEventListener('submit', function (e) {
      var msg = form.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
      busy(form);
    });
  });
  $$('form[data-validate][data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!e.defaultPrevented && !window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    }, true);
  });

  // A second button in a form that needs its own confirmation (e.g. Cancel next to Reject).
  $$('button[data-confirm-click]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-confirm-click'))) e.preventDefault(); });
  });

  // Booking step 3: show the price estimate as the customer types a weight.
  var est = $('[data-est-kg]');
  if (est) {
    var out = $('[data-est-out]'), base = out ? out.textContent : '';
    var showEst = function () {
      var kgv = parseFloat(est.value), price = parseFloat(est.getAttribute('data-price'));
      if (!out) return;
      out.textContent = kgv > 0 ? 'Estimate: ' + est.getAttribute('data-currency') + (kgv * price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '. The final price uses the weight at drop-off.' : base;
    };
    est.addEventListener('input', showEst);
    showEst();
  }

  // ---------- New order ----------
  var orderForm = $('[data-new-order]');
  function newOrderCustomerOk(form) {
    var mode = form.querySelector('input[name=mode]:checked');
    if (!mode || mode.value !== 'existing') return true;
    var ok = !!$('[data-customer-id]', form).value;
    var box = $('[data-search-box]', form);
    var input = $('[data-customer-search]', form);
    showError(input, ok ? '' : 'Search and pick a customer, or choose New customer.');
    if (!ok) box.hidden = false;
    return ok;
  }
  if (orderForm) {
    var currency = $('[data-sum-total]').getAttribute('data-currency');
    var fmt = function (n) { return currency + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var update = function () {
      var svc = orderForm.querySelector('input[name=service_id]:checked');
      var w = parseFloat($('[data-weight]').value) || 0;
      var paid = parseFloat($('[data-pay]').value) || 0;
      var price = svc ? parseFloat(svc.getAttribute('data-price')) : 0;
      var total = Math.round(w * price * 100) / 100;
      $('[data-sum-service]').textContent = svc ? svc.getAttribute('data-name') : '—';
      $('[data-sum-weight]').textContent = w ? w + ' kg' : '—';
      $('[data-sum-rate]').textContent = svc ? fmt(price) + '/kg' : '—';
      $('[data-sum-paid]').textContent = fmt(paid);
      $('[data-sum-total]').textContent = fmt(total);
      $('[data-pay]').max = total ? total.toFixed(2) : '';
    };
    orderForm.addEventListener('input', update);
    orderForm.addEventListener('change', update);
    update();

    var search = $('[data-customer-search]');
    var results = $('[data-results]');
    var hiddenId = $('[data-customer-id]');
    var picked = $('[data-picked]');
    var box = $('[data-search-box]');
    var timer = null, seq = 0;
    var el = function (tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
    var pick = function (c) {
      hiddenId.value = c.id;
      $('[data-picked-ini]').textContent = c.initials;
      $('[data-picked-name]').textContent = c.name;
      $('[data-picked-phone]').textContent = c.phone;
      picked.classList.remove('is-empty');
      box.hidden = true;
      results.innerHTML = '';
      showError(search, '');
      var w = $('[data-weight]'); if (w) w.focus();
    };
    $('[data-unpick]').addEventListener('click', function () {
      hiddenId.value = '';
      picked.classList.add('is-empty');
      box.hidden = false;
      search.value = '';
      search.focus();
    });
    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    search.addEventListener('input', function () {
      clearTimeout(timer);
      var term = search.value.trim();
      if (term.length < 2) { results.innerHTML = ''; return; }
      timer = setTimeout(function () {
        var mine = ++seq;
        fetch(search.getAttribute('data-endpoint') + '?q=' + encodeURIComponent(term), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (rows) {
            if (mine !== seq) return;
            results.innerHTML = '';
            if (!rows.length) {
              var li = el('li', 'note', 'No match. Choose New customer to add them.');
              results.appendChild(li);
              return;
            }
            rows.forEach(function (c) {
              var li = el('li'), b = el('button');
              b.type = 'button';
              b.appendChild(el('span', 'avatar', c.initials));
              var main = el('span', 'list-main');
              main.appendChild(el('b', null, c.name));
              main.appendChild(el('span', 'muted-sm', c.phone + ' · ' + c.orders + (c.orders === 1 ? ' order' : ' orders')));
              b.appendChild(main);
              b.addEventListener('click', function () { pick(c); });
              li.appendChild(b);
              results.appendChild(li);
            });
          })
          .catch(function () { results.innerHTML = ''; results.appendChild(el('li', 'note', "Couldn't search. Check that the server is running.")); });
      }, 180);
    });
  }
})();
