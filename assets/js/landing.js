// Landing page motion and navigation. The page is complete without it.
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var EASE = 'cubic-bezier(0.32,0.72,0,1)'; // critically damped: settles without overshoot
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var canAnimate = function (el) { return el && typeof el.animate === 'function' && !reduce.matches; };

  // Scroll reveal, staggered among siblings (hierarchy: content arrives in reading order).
  var revealed = function (el) { el.style.opacity = 1; };
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        var el = e.target;
        if (!canAnimate(el)) return revealed(el);
        var sibs = $$('[data-reveal]', el.parentElement).filter(function (n) { return n.parentElement === el.parentElement; });
        var i = Math.max(0, sibs.indexOf(el));
        el.animate([{ opacity: 0, transform: 'translateY(18px)' }, { opacity: 1, transform: 'none' }],
          { duration: 700, delay: i * 70 + (el.getAttribute('data-reveal') === '2' ? 120 : 0), easing: EASE, fill: 'backwards' });
        revealed(el);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
    $$('[data-reveal]').forEach(function (n) { io.observe(n); });
  } else {
    $$('[data-reveal]').forEach(revealed);
  }

  // Nav: underline follows the section in view.
  var links = $$('[data-nav-link]');
  var setActive = function (key) {
    links.forEach(function (a) {
      if (a.getAttribute('data-nav-link') === key) a.setAttribute('aria-current', 'true');
      else a.removeAttribute('aria-current');
    });
  };
  if ('IntersectionObserver' in window) {
    var navIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) setActive(e.target.getAttribute('data-section')); });
    }, { rootMargin: '-40% 0px -55% 0px' });
    $$('[data-section]').forEach(function (s) { navIo.observe(s); });
  }
  setActive('home');

  // Mobile menu: drops from the bar it belongs to and leaves the same way.
  var menuBtn = document.querySelector('[data-l-menu]');
  var menu = document.getElementById('l-menu');
  var openMenu = function () {
    menu.hidden = false;
    menuBtn.setAttribute('aria-expanded', 'true');
    if (canAnimate(menu)) menu.animate([{ opacity: 0, transform: 'translateY(-8px)' }, { opacity: 1, transform: 'none' }], { duration: 300, easing: EASE });
  };
  var closeMenu = function () {
    if (menu.hidden) return;
    menuBtn.setAttribute('aria-expanded', 'false');
    if (!canAnimate(menu)) { menu.hidden = true; return; }
    menu.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-8px)' }], { duration: 220, easing: EASE }).onfinish = function () { menu.hidden = true; };
  };
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', function () { menu.hidden ? openMenu() : closeMenu(); });
    $$('a', menu).forEach(function (a) { a.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { closeMenu(); menuBtn.focus(); } });
  }

  // Example order moving through the six statuses (storytelling: the core idea of the product).
  var STAGES = [
    ['Received', 'Logged at the counter, 9:12 AM'], ['Washing', 'Started 9:40 AM'], ['Drying', 'Started 10:25 AM'],
    ['Folding', 'Started 11:05 AM'], ['Ready for Pickup', 'Ready since 11:30 AM'], ['Completed', 'Claimed 4:15 PM']
  ];
  var statusEl = document.querySelector('[data-hero-status]');
  var noteEl = document.querySelector('[data-hero-note]');
  var textEl = document.querySelector('[data-hero-text]');
  var bars = $$('.l-sc-bars span');
  var mark = document.querySelector('[data-hero-mark] svg.lm');
  var band = $$('[data-statuses] li');
  var stage = 3;
  var show = function (s) {
    statusEl.textContent = STAGES[s][0];
    noteEl.textContent = STAGES[s][1];
    var cls = function (i) { return i < s ? 'done' : i === s ? 'cur' : ''; };
    bars.forEach(function (b, i) { b.className = cls(i); });
    band.forEach(function (li, i) { li.className = cls(i); });
    if (mark) mark.setAttribute('data-lm-state', s >= 4 ? 'done' : s === 0 ? 'pending' : 'processing');
    if (canAnimate(textEl)) textEl.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { duration: 420, easing: EASE });
  };
  var timer = null;
  var run = function () {
    clearInterval(timer);
    if (reduce.matches || !statusEl) return;
    timer = setInterval(function () { if (!document.hidden) { stage = (stage + 1) % 6; show(stage); } }, 2200);
  };
  run();

  // Floating bubbles stop under reduced motion. (The washer itself is handled by motion.js.)
  var bubbleAnims = [];
  var applyMotion = function () {
    bubbleAnims.forEach(function (a) { a.cancel(); });
    bubbleAnims = [];
    if (reduce.matches) return;
    $$('[data-bubble]').forEach(function (n, i) {
      if (typeof n.animate !== 'function') return;
      bubbleAnims.push(n.animate([{ transform: 'translateY(0) scale(1)' }, { transform: 'translateY(' + (-8 - i * 3) + 'px) scale(' + (1 + (i % 2) * 0.05) + ')' }],
        { duration: 5200 + i * 900, delay: i * -700, direction: 'alternate', iterations: Infinity, easing: 'ease-in-out' }));
    });
  };
  applyMotion();
  var onPref = function () { applyMotion(); run(); };
  if (reduce.addEventListener) reduce.addEventListener('change', onPref);
})();
