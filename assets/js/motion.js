// Shared motion for LaundryTrack: logo mark, washer, sign-in entrance and the post sign-in intro.
// Ported from the Claude Design components "LaundryTrack Logo Motion" and "Processing Icon".
// Every page works without this file; it only adds movement.
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var EASE = 'cubic-bezier(0.32,0.72,0,1)';
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var canAnimate = function (el) { return el && typeof el.animate === 'function'; };
  var onPref = function (fn) { if (reduce.addEventListener) reduce.addEventListener('change', fn); else if (reduce.addListener) reduce.addListener(fn); };

  // ---------- Logo motion (5 s loop: lift, fold, sweep) ----------
  var cl = function (v) { return Math.min(1, Math.max(0, v)); };
  var io = function (t, a, b) { var u = cl((t - a) / (b - a)); return u < 0.5 ? 4 * u * u * u : 1 - Math.pow(-2 * u + 2, 3) / 2; };
  var sw = function (t, a, b) { return -(Math.cos(Math.PI * cl((t - a) / (b - a))) - 1) / 2; };
  var st = function (t, a, b) { var u = cl((t - a) / (b - a)); return 1 + 2.2 * Math.pow(u - 1, 3) + 1.2 * Math.pow(u - 1, 2); };
  function applyFrame(m, T) {
    var lift = io(T, 1, 1.8) - io(T, 4, 4.75);
    var pulse = Math.sin(Math.PI * sw(T, 2, 3)) * 0.012;
    var sc = 1 + 0.025 * lift + pulse, y = -8 * lift, f = 1;
    if (T >= 1.2 && T < 2) f = 1 - 0.07 * Math.sin(Math.PI * sw(T, 1.2, 2));
    else if (T >= 3 && T < 3.45) f = 1 - 0.5 * io(T, 3, 3.45);
    else if (T >= 3.45 && T < 4) f = 0.5 + 0.5 * st(T, 3.45, 4);
    m.shirt.setAttribute('transform', 'translate(256 ' + (371 + y).toFixed(3) + ') scale(' + sc.toFixed(4) + ') translate(-256 -371)');
    m.fold.setAttribute('transform', 'translate(358 334) rotate(132.9) scale(1 ' + f.toFixed(4) + ') rotate(-132.9) translate(-358 -334)');
    var rp = cl((T - 1.8) / 1.7);
    if (rp > 0 && rp < 1) {
      var head = -90 + 380 * sw(T, 1.8, 3.5), len = 4 + 120 * Math.sin(Math.PI * rp), a0 = head - len;
      var P = function (d) { return [256 + 212 * Math.cos(d * Math.PI / 180), 256 + 212 * Math.sin(d * Math.PI / 180)]; };
      var p0 = P(a0), p1 = P(head);
      m.arc.setAttribute('d', 'M' + p0[0].toFixed(2) + ' ' + p0[1].toFixed(2) + 'A212 212 0 0 1 ' + p1[0].toFixed(2) + ' ' + p1[1].toFixed(2));
      m.arc.setAttribute('opacity', Math.min(1, Math.sin(Math.PI * rp) * 2.2).toFixed(3));
    } else m.arc.setAttribute('opacity', '0');
  }
  var marks = $$('svg.lm').map(function (el) {
    return { el: el, shirt: el.querySelector('[data-lm="shirt"]'), fold: el.querySelector('[data-lm="fold"]'), arc: el.querySelector('[data-lm="arc"]'), vis: true, rest: false };
  }).filter(function (m) { return m.shirt && m.fold && m.arc; });
  var raf = null, t0 = 0;
  function tick(now) {
    raf = null;
    if (!t0) t0 = now;
    var T = ((now - t0) / 1000) % 5, any = false;
    marks.forEach(function (m) {
      var run = m.vis && !reduce.matches && m.el.getAttribute('data-lm-state') === 'processing';
      if (run) { any = true; m.rest = false; if (!document.hidden) applyFrame(m, T); }
      else if (!m.rest) { applyFrame(m, 0); m.rest = true; }
    });
    if (any) raf = requestAnimationFrame(tick);
  }
  // Called whenever something may need the loop again (visibility, state, preference).
  var wakeMarks = function () { if (!raf) raf = requestAnimationFrame(tick); };
  if (marks.length) {
    if ('IntersectionObserver' in window) {
      var mio = new IntersectionObserver(function (es) {
        es.forEach(function (e) { marks.forEach(function (m) { if (m.el === e.target) m.vis = e.isIntersecting; }); });
        wakeMarks();
      });
      marks.forEach(function (m) { mio.observe(m.el); });
    }
    if ('MutationObserver' in window) {
      var mo = new MutationObserver(wakeMarks);
      marks.forEach(function (m) { mo.observe(m.el, { attributes: true, attributeFilter: ['data-lm-state'] }); });
    }
    document.addEventListener('visibilitychange', wakeMarks);
    onPref(wakeMarks);
    wakeMarks();
  }
  window.LTMotion = { wake: wakeMarks };

  // ---------- Washer (SMIL): run only when visible and motion is allowed ----------
  var washers = $$('svg[data-washer]');
  var washerVis = new Map();
  var syncWasher = function (svg) {
    if (!svg.pauseAnimations) return;
    if (reduce.matches) { svg.pauseAnimations(); svg.setCurrentTime(0); }
    else if (washerVis.get(svg) === false) svg.pauseAnimations();
    else svg.unpauseAnimations();
  };
  if (washers.length) {
    if ('IntersectionObserver' in window) {
      var wio = new IntersectionObserver(function (es) {
        es.forEach(function (e) { washerVis.set(e.target, e.isIntersecting); syncWasher(e.target); });
      });
      washers.forEach(function (w) { wio.observe(w); });
    }
    washers.forEach(syncWasher);
    onPref(function () { washers.forEach(syncWasher); });
  }

  // ---------- Sign-in card entrance (login, setup, track form) ----------
  var card = document.querySelector('.split-card');
  if (card && canAnimate(card)) {
    var calm = reduce.matches;
    card.animate(calm ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, transform: 'translateY(12px) scale(.985)' }, { opacity: 1, transform: 'none' }],
      { duration: calm ? 200 : 520, easing: EASE, fill: 'backwards' });
    if (!calm) {
      $$('.split-panel-body > *, .split-inner > *', card).forEach(function (n, i) {
        n.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }],
          { duration: 560, delay: 120 + i * 60, easing: EASE, fill: 'backwards' });
      });
      var w = card.querySelector('.split-washer');
      if (w) w.animate([{ opacity: 0, transform: 'scale(.9)' }, { opacity: 1, transform: 'none' }], { duration: 640, delay: 80, easing: EASE, fill: 'backwards' });
      // Step dots light up in order, up to this page's step.
      $$('.step-strip li.on .dot', card).forEach(function (d, i) {
        d.animate([{ transform: 'scale(.4)', opacity: 0.3 }, { transform: 'scale(1.25)', offset: 0.6 }, { transform: 'scale(1)', opacity: 1 }],
          { duration: 460, delay: 520 + i * 110, easing: EASE, fill: 'backwards' });
      });
      var strip = card.querySelector('.step-strip');
      if (strip) strip.classList.add('strip-anim');
    }
  }

  // ---------- Intro after sign-in (Web v2 loading screen) ----------
  var intro = document.querySelector('[data-intro-screen]');
  // A welcome is nice once; staff sign in many times a day, so after the first time today it is skipped.
  var today = new Date().toDateString(), seen = null;
  try { seen = localStorage.getItem('lt-intro'); localStorage.setItem('lt-intro', today); } catch (e) { /* private mode */ }
  if (intro && seen === today) { intro.remove(); intro = null; }
  if (intro) {
    var calmI = reduce.matches;
    intro.hidden = false;
    document.documentElement.classList.add('intro-on');
    var q = function (k) { return intro.querySelector('[data-intro="' + k + '"]'); };
    var rise = function (k, delay) {
      var n = q(k);
      if (canAnimate(n)) n.animate(calmI ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'none' }],
        { duration: calmI ? 200 : 560, delay: calmI ? 0 : delay, easing: EASE, fill: 'backwards' });
    };
    var ic = q('icon');
    if (canAnimate(ic)) ic.animate(calmI ? [{ opacity: 0 }, { opacity: 1 }] : [{ opacity: 0, transform: 'scale(.86)' }, { opacity: 1, transform: 'none' }],
      { duration: calmI ? 200 : 640, easing: EASE, fill: 'backwards' });
    rise('word', 160); rise('line', 260); rise('sub', 340); rise('bar', 420);
    var fill = q('fill');
    if (canAnimate(fill)) fill.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }],
      { duration: calmI ? 600 : 1600, delay: calmI ? 0 : 420, easing: 'cubic-bezier(0.45,0,0.2,1)', fill: 'forwards' });
    wakeMarks();
    var done = false, timer;
    var finish = function () {
      if (done) return;
      done = true;
      clearTimeout(timer);
      document.removeEventListener('keydown', finish, true);
      var remove = function () {
        intro.remove();
        document.documentElement.classList.remove('intro-on');
        var main = document.getElementById('main');
        if (main) { main.setAttribute('tabindex', '-1'); main.focus({ preventScroll: true }); }
      };
      if (!canAnimate(intro)) return remove();
      // Leaves from wherever it is now, so an early tap never jumps.
      intro.animate(calmI ? [{ opacity: 1 }, { opacity: 0 }] : [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(1.03)' }],
        { duration: calmI ? 200 : 420, easing: EASE, fill: 'forwards' }).onfinish = remove;
    };
    timer = setTimeout(finish, calmI ? 700 : 2100);
    intro.addEventListener('pointerdown', finish);
    document.addEventListener('keydown', finish, true);
  }
})();
