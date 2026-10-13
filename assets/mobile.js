/*!
 * Reptilia Motion: comportamientos solo para celular.
 * - Accesos rápidos "Planes" y "Armá tu presupuesto" junto al botón del encabezado.
 * - Planes deslizables con puntos indicadores, empezando por el recomendado.
 */
(function () {
  'use strict';

  var small = window.matchMedia('(max-width: 921px)');
  var phone = window.matchMedia('(max-width: 767px)');
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function smoothTo(node, offset) {
    if (!node) return;
    var top = node.getBoundingClientRect().top + (window.pageYOffset || 0) - (offset || 0);
    window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });
  }

  /* Ancla para la sección de planes (la portada original no tiene una) */
  var packs = document.querySelector('.elementor-element-01b9c67');
  if (packs && !packs.id) packs.id = 'planes';

  /* ---------------- Encabezado ---------------- */
  function buildNav() {
    var right = document.querySelector('.ast-mobile-header-wrap .site-header-primary-section-right');
    if (!right || right.querySelector('.rp-mnav')) return;
    var nav = document.createElement('nav');
    nav.className = 'rp-mnav';
    nav.setAttribute('aria-label', 'Accesos rápidos');
    nav.innerHTML =
      '<a class="rp-mnav__planes" href="#planes">Planes</a>' +
      '<a class="rp-mnav__calc" href="#calculadora">Armá tu<br>presupuesto</a>';
    right.insertBefore(nav, right.firstChild);
    nav.querySelector('.rp-mnav__planes').addEventListener('click', function (e) {
      if (!packs) return;
      e.preventDefault();
      smoothTo(packs, 8);
    });
    /* "#calculadora" lo abre calc.js; pide los datos antes de mostrar los servicios */
  }
  buildNav();

  /* ---------------- Planes deslizables ---------------- */
  var grid = document.querySelector('.rp-packs__grid');
  if (!grid) return;

  var cards = Array.prototype.slice.call(grid.children).filter(function (c) { return c.classList.contains('rp-pack'); });
  if (cards.length < 2) return;

  var dots = document.createElement('div');
  dots.className = 'rp-packs__dots';
  dots.setAttribute('role', 'tablist');
  dots.setAttribute('aria-label', 'Planes');
  cards.forEach(function (c, i) {
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Ver plan ' + (i + 1));
    b.addEventListener('click', function () { centerCard(i, true); });
    dots.appendChild(b);
  });
  /* Solo se muestran en teléfonos; en escritorio la hoja de estilos móvil ni se carga, así que se oculta desde acá */
  function syncDots() { dots.hidden = !phone.matches; }
  syncDots();
  if (phone.addEventListener) phone.addEventListener('change', syncDots); else if (phone.addListener) phone.addListener(syncDots);
  grid.parentNode.insertBefore(dots, grid.nextSibling);

  function centerCard(i, smooth) {
    if (!phone.matches) return;
    var c = cards[i];
    var left = c.offsetLeft - (grid.clientWidth - c.offsetWidth) / 2;
    grid.scrollTo({ left: left, behavior: smooth && !reduce ? 'smooth' : 'auto' });
  }

  function current() {
    var mid = grid.scrollLeft + grid.clientWidth / 2;
    var best = 0, bestD = Infinity;
    cards.forEach(function (c, i) {
      var d = Math.abs(c.offsetLeft + c.offsetWidth / 2 - mid);
      if (d < bestD) { bestD = d; best = i; }
    });
    return best;
  }

  var ticking = false;
  function paint() {
    ticking = false;
    var a = current();
    Array.prototype.forEach.call(dots.children, function (b, i) {
      b.classList.toggle('is-active', i === a);
      b.setAttribute('aria-selected', i === a ? 'true' : 'false');
    });
  }
  grid.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(paint); }
  }, { passive: true });

  /* Arranca en el plan recomendado (el del medio), fuera de pantalla y sin animación */
  var featured = cards.findIndex(function (c) { return c.classList.contains('rp-pack--featured') || c.classList.contains('rp-pack--muda'); });
  function start() { centerCard(featured > -1 ? featured : 0, false); paint(); }
  start();
  window.addEventListener('load', function () { setTimeout(start, 350); }, { once: true });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(start);
  window.addEventListener('resize', function () { setTimeout(paint, 120); });
})();
