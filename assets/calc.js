/*!
 * Reptilia — Calculadora de plan personalizado (#calculadora)
 * Panel a pantalla completa. Datos: window.RP_CALC (catálogo, cotización y API).
 * El total definitivo lo calcula el servidor; acá es una vista previa idéntica.
 */
(function () {
  'use strict';
  var C = window.RP_CALC;
  if (!C) return;

  var html = document.documentElement;
  var KEY_LEAD = 'rpCalcLead';
  var KEY_STATE = 'rpCalcState';
  var state = { qty: {}, segment: 'pyme', currency: 'ARS', filter: 'all' };
  var lead = null;
  var root = null;
  var lastFocus = null;
  var lastTier = 0;

  try { lead = JSON.parse(sessionStorage.getItem(KEY_LEAD) || 'null'); } catch (e) { lead = null; }
  if (lead && !lead.id) lead = null; // datos viejos sin contacto guardado
  try { Object.assign(state, JSON.parse(sessionStorage.getItem(KEY_STATE) || '{}')); } catch (e) { /* sin estado previo */ }

  var byId = {};
  C.services.forEach(function (s) { byId[s.id] = s; });

  function save() {
    try { sessionStorage.setItem(KEY_STATE, JSON.stringify(state)); } catch (e) { /* modo privado */ }
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function mult() {
    var m = 1;
    C.segments.forEach(function (s) { if (s.id === state.segment) m = parseFloat(s.mult); });
    return m;
  }
  /* Misma fórmula que el servidor */
  function unit(base) {
    var ars = Math.round(base * mult() / 100) * 100;
    return state.currency === 'USD' ? Math.round(ars / parseFloat(C.rate)) : ars;
  }
  function money(v) {
    var n = Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (state.currency === 'USD' ? 'US$ ' : '$') + n;
  }
  function totals() {
    var units = 0, sub = 0, lines = [];
    C.services.forEach(function (s) {
      var q = state.qty[s.id] || 0;
      if (!q) return;
      var u = unit(s.price);
      units += q;
      sub += u * q;
      lines.push({ s: s, q: q, total: u * q });
    });
    var pct = 0;
    C.discounts.forEach(function (d) { if (units >= d.min) pct = d.pct; });
    var disc = Math.round(sub * pct / 100);
    return { units: units, sub: sub, pct: pct, disc: disc, total: sub - disc, lines: lines };
  }

  /* ---------------------------------------------------------------- */
  /* Estructura del panel                                              */
  /* ---------------------------------------------------------------- */
  function build() {
    root = document.createElement('div');
    root.className = 'rp-calc';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-labelledby', 'rp-calc-title');
    root.innerHTML =
      '<div class="rp-calc__bg" aria-hidden="true"></div>' +
      '<header class="rp-calc__top">' +
        '<div class="rp-calc__brand">' + (C.logo ? '<img src="' + esc(C.logo) + '" alt="" width="34" height="34">' : '') +
        '<div><span class="rp-calc__eyebrow">Plan personalizado</span><h2 id="rp-calc-title">Armá tu plan a medida</h2></div></div>' +
        '<button type="button" class="rp-calc__close" aria-label="Cerrar calculadora">&times;</button>' +
      '</header>' +
      '<div class="rp-calc__body"></div>';
    document.body.appendChild(root);

    root.querySelector('.rp-calc__close').addEventListener('click', close);
    root.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); if (root.querySelector('.rp-thanks.is-open')) closeThanks(); else close(); }
      if (e.key === 'Tab') trapFocus(e);
    });
  }

  function trapFocus(e) {
    var f = Array.prototype.filter.call(root.querySelectorAll('button, a[href], input, [tabindex="0"]'), function (n) {
      return !n.disabled && n.offsetParent !== null;
    });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function open() {
    if (!root) build();
    lastFocus = document.activeElement;
    render();
    html.classList.add('rp-calc-open');
    requestAnimationFrame(function () {
      root.classList.add('is-open');
      var first = root.querySelector('.rp-gate input, .rp-calc__seg button');
      (first || root.querySelector('.rp-calc__close')).focus({ preventScroll: true });
    });
    if (location.hash !== '#calculadora') history.replaceState(null, '', '#calculadora');
  }
  function close() {
    if (!root) return;
    root.classList.remove('is-open');
    html.classList.remove('rp-calc-open');
    if (location.hash === '#calculadora') history.replaceState(null, '', location.pathname + location.search);
    if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
  }

  function render() {
    var body = root.querySelector('.rp-calc__body');
    body.innerHTML = lead ? mainView() : gateView();
    if (lead) bindMain(body); else bindGate(body);
  }

  /* ---------------------------------------------------------------- */
  /* Paso 1: datos de contacto                                          */
  /* ---------------------------------------------------------------- */
  function gateView() {
    return '<form class="rp-gate" novalidate>' +
      '<div class="rp-gate__intro"><h3>Antes de empezar, contanos quién sos</h3>' +
      '<p>Así te enviamos tu presupuesto en PDF y podemos acompañarte con lo que elijas.</p></div>' +
      field('name', 'Nombre y apellido', 'text', 'name', '') +
      field('phone', 'WhatsApp', 'tel', 'tel', '+54 9 341 …') +
      field('email', 'Email', 'email', 'email', 'tu@email.com') +
      '<label class="rp-check"><input type="checkbox" name="consent" required><span>Acepto que Reptilia guarde estos datos para enviarme el presupuesto y contactarme.</span></label>' +
      '<p class="rp-gate__error" role="alert" hidden></p>' +
      '<button type="submit" class="rp-btn rp-btn--primary rp-btn--big">Empezar a armar mi plan <span aria-hidden="true">→</span></button>' +
      '</form>';
  }
  function field(name, label, type, ac, ph) {
    return '<label class="rp-field"><span>' + label + '</span><input name="' + name + '" type="' + type + '" autocomplete="' + ac + '" placeholder="' + esc(ph) + '" required></label>';
  }
  function bindGate(body) {
    var form = body.querySelector('.rp-gate');
    var shownAt = Date.now();
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      var data = { name: (fd.get('name') || '').trim(), phone: (fd.get('phone') || '').trim(), email: (fd.get('email') || '').trim(), consent: fd.get('consent') ? 1 : 0, h: 'rp1', t: Date.now() - shownAt };
      var err = form.querySelector('.rp-gate__error');
      var problems = [];
      if (data.name.length < 2) problems.push('tu nombre');
      if ((data.phone.match(/\d/g) || []).length < 8) problems.push('un WhatsApp válido');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) problems.push('un email válido');
      if (!data.consent) problems.push('aceptar la casilla de consentimiento');
      if (problems.length) {
        err.textContent = 'Falta ' + problems.join(', ') + '.';
        err.hidden = false;
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.classList.add('is-loading');
      post('lead', data).then(function (res) {
        if (!res.ok) throw new Error(res.error || 'No pudimos guardar tus datos.');
        lead = { id: res.lead, token: res.token, name: res.name || data.name, email: data.email, phone: data.phone };
        try { sessionStorage.setItem(KEY_LEAD, JSON.stringify(lead)); } catch (e2) { /* modo privado */ }
        render();
        var first = root.querySelector('.rp-calc__seg button');
        if (first) first.focus({ preventScroll: true });
      }).catch(function (e3) {
        err.textContent = e3.message;
        err.hidden = false;
        btn.disabled = false;
        btn.classList.remove('is-loading');
      });
    });
  }

  /* ---------------------------------------------------------------- */
  /* Paso 2: calculadora                                                */
  /* ---------------------------------------------------------------- */
  function mainView() {
    var seg = C.segments.map(function (s) {
      return '<button type="button" data-seg="' + s.id + '" aria-pressed="' + (state.segment === s.id) + '"><strong>' + esc(s.label) + '</strong><small>' + esc(s.hint) + '</small></button>';
    }).join('');
    var cur = ['ARS', 'USD'].map(function (c) {
      return '<button type="button" data-cur="' + c + '" aria-pressed="' + (state.currency === c) + '">' + c + '</button>';
    }).join('');
    var chips = '<button type="button" data-filter="all" aria-pressed="' + (state.filter === 'all') + '">Todos</button>' +
      C.categories.map(function (c) {
        return '<button type="button" data-filter="' + c.id + '" aria-pressed="' + (state.filter === c.id) + '">' + esc(c.label) + '</button>';
      }).join('');
    var tiers = C.discounts.map(function (d) { return '<span data-min="' + d.min + '"><b>' + d.pct + '%</b><small>' + d.min + ' ítems</small></span>'; }).join('');
    return '<div class="rp-calc__grid">' +
      '<section class="rp-calc__main" aria-label="Servicios">' +
        '<p class="rp-calc__hello">Hola, <strong>' + esc(lead.name.split(' ')[0]) + '</strong>. Elegí los servicios y cantidades: el total se actualiza al instante.</p>' +
        '<div class="rp-calc__controls">' +
          '<div class="rp-calc__group"><span class="rp-calc__label">Tipo de negocio</span><div class="rp-calc__seg">' + seg + '</div></div>' +
          '<div class="rp-calc__group rp-calc__group--cur"><span class="rp-calc__label">Moneda</span><div class="rp-calc__cur">' + cur + '</div></div>' +
        '</div>' +
        '<div class="rp-calc__chips" role="toolbar" aria-label="Filtrar por categoría">' + chips + '</div>' +
        '<div class="rp-calc__list"></div>' +
      '</section>' +
      '<aside class="rp-sum" aria-label="Tu presupuesto">' +
        '<button type="button" class="rp-sum__toggle" aria-expanded="false"><span>Tu presupuesto · <b class="rp-sum__count">0</b> ítems</span><strong class="rp-sum__mini">$0</strong></button>' +
        '<div class="rp-sum__panel">' +
          '<h3>Tu presupuesto</h3>' +
          '<ul class="rp-sum__lines"></ul>' +
          '<div class="rp-tier"><div class="rp-tier__head"><span class="rp-tier__msg"></span></div>' +
            '<div class="rp-tier__bar"><i></i></div><div class="rp-tier__marks">' + tiers + '</div></div>' +
          '<dl class="rp-sum__totals">' +
            '<div><dt>Subtotal</dt><dd class="rp-sum__sub">$0</dd></div>' +
            '<div class="rp-sum__disc" hidden><dt>Descuento <span class="rp-sum__badge"></span></dt><dd></dd></div>' +
            '<div class="rp-sum__total"><dt>Total</dt><dd><span class="rp-sum__amount">$0</span></dd></div>' +
          '</dl>' +
          (state.currency === 'USD' ? '<p class="rp-sum__note">Conversión al dólar oficial: US$ 1 = $' + Math.round(C.rate).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '</p>' : '') +
          '<p class="rp-sum__error" role="alert" hidden></p>' +
          '<button type="button" class="rp-btn rp-btn--primary rp-btn--big rp-sum__go" disabled>Finalizar presupuesto <span aria-hidden="true">→</span></button>' +
          '<p class="rp-sum__fine">Recibís el detalle en PDF por email. Valores de referencia, sujetos a confirmación.</p>' +
        '</div>' +
      '</aside>' +
    '</div>' +
    '<div class="rp-thanks" role="alertdialog" aria-modal="true" aria-labelledby="rp-thanks-title" hidden></div>';
  }

  function listView() {
    var cats = {};
    C.categories.forEach(function (c) { cats[c.id] = c.label; });
    return C.services.filter(function (s) { return state.filter === 'all' || s.cat === state.filter; }).map(function (s) {
      var q = state.qty[s.id] || 0;
      var save = '';
      if (s.of && byId[s.of]) {
        var full = byId[s.of].price * s.qty;
        var pct = Math.round((1 - s.price / full) * 100);
        if (pct > 0) save = '<span class="rp-svc__save">Ahorrás ' + pct + '%</span><s class="rp-svc__was">' + money(unit(byId[s.of].price) * s.qty) + '</s>';
      }
      return '<article class="rp-svc' + (q ? ' is-on' : '') + (s.of ? ' rp-svc--pack' : '') + '" data-id="' + s.id + '">' +
        '<div class="rp-svc__info"><span class="rp-svc__cat">' + esc(cats[s.cat] || '') + (s.of ? ' · Pack' : '') + '</span>' +
        '<h4>' + esc(s.name) + '</h4><p>' + esc(s.desc) + '</p></div>' +
        '<div class="rp-svc__buy"><div class="rp-svc__price">' + save + '<strong>' + money(unit(s.price)) + '</strong></div>' +
        '<div class="rp-step" role="group" aria-label="Cantidad de ' + esc(s.name) + '">' +
          '<button type="button" data-step="-1" aria-label="Quitar uno"' + (q ? '' : ' disabled') + '>−</button>' +
          '<output aria-live="polite">' + q + '</output>' +
          '<button type="button" data-step="1" aria-label="Agregar uno">+</button>' +
        '</div></div></article>';
    }).join('');
  }

  function bindMain(body) {
    var list = body.querySelector('.rp-calc__list');
    list.innerHTML = listView();

    body.querySelector('.rp-calc__seg').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-seg]');
      if (!b) return;
      state.segment = b.getAttribute('data-seg');
      save();
      refreshAll(body);
    });
    body.querySelector('.rp-calc__cur').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-cur]');
      if (!b) return;
      state.currency = b.getAttribute('data-cur');
      save();
      render(); // también actualiza la nota de cotización
    });
    body.querySelector('.rp-calc__chips').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-filter]');
      if (!b) return;
      state.filter = b.getAttribute('data-filter');
      save();
      body.querySelectorAll('.rp-calc__chips button').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      list.innerHTML = listView();
      list.classList.remove('is-swap');
      void list.offsetWidth;
      list.classList.add('is-swap');
    });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-step]');
      if (!b) return;
      var card = b.closest('.rp-svc');
      var id = card.getAttribute('data-id');
      var q = Math.max(0, Math.min(50, (state.qty[id] || 0) + parseInt(b.getAttribute('data-step'), 10)));
      if (q) state.qty[id] = q; else delete state.qty[id];
      save();
      card.classList.toggle('is-on', q > 0);
      card.querySelector('output').textContent = q;
      card.querySelector('[data-step="-1"]').disabled = !q;
      card.classList.remove('is-bump');
      void card.offsetWidth;
      card.classList.add('is-bump');
      updateSummary(body);
    });
    body.querySelector('.rp-sum__lines').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-remove]');
      if (!b) return;
      delete state.qty[b.getAttribute('data-remove')];
      save();
      list.innerHTML = listView();
      updateSummary(body);
    });
    body.querySelector('.rp-sum__toggle').addEventListener('click', function () {
      var sum = body.querySelector('.rp-sum');
      var openNow = !sum.classList.contains('is-expanded');
      sum.classList.toggle('is-expanded', openNow);
      this.setAttribute('aria-expanded', String(openNow));
    });
    body.querySelector('.rp-sum__go').addEventListener('click', function () { finalize(body, this); });
    lastTier = totals().pct;
    updateSummary(body, true);
  }

  function refreshAll(body) {
    body.querySelectorAll('.rp-calc__seg button').forEach(function (x) { x.setAttribute('aria-pressed', String(x.getAttribute('data-seg') === state.segment)); });
    body.querySelector('.rp-calc__list').innerHTML = listView();
    updateSummary(body);
  }

  var shown = 0;
  function animateAmount(node, to) {
    var from = shown;
    var start = performance.now();
    var dur = 520;
    (function step(now) {
      var p = Math.min(1, (now - start) / dur);
      var v = from + (to - from) * (1 - Math.pow(1 - p, 3));
      node.textContent = money(v);
      if (p < 1) requestAnimationFrame(step); else shown = to;
    })(start);
  }

  function updateSummary(body, instant) {
    var t = totals();
    var lines = body.querySelector('.rp-sum__lines');
    lines.innerHTML = t.lines.length ? t.lines.map(function (l) {
      return '<li><span class="rp-sum__name">' + esc(l.s.name) + ' <em>× ' + l.q + '</em></span><span class="rp-sum__val">' + money(l.total) + '</span>' +
        '<button type="button" data-remove="' + l.s.id + '" aria-label="Quitar ' + esc(l.s.name) + '">&times;</button></li>';
    }).join('') : '<li class="rp-sum__empty">Todavía no sumaste servicios. Usá el botón + de cada uno.</li>';

    body.querySelector('.rp-sum__count').textContent = t.units;
    body.querySelector('.rp-sum__mini').textContent = money(t.total);
    body.querySelector('.rp-sum__sub').textContent = money(t.sub);
    var disc = body.querySelector('.rp-sum__disc');
    disc.hidden = !t.pct;
    if (t.pct) {
      disc.querySelector('.rp-sum__badge').textContent = '−' + t.pct + '%';
      disc.querySelector('dd').textContent = '−' + money(t.disc);
    }
    var amount = body.querySelector('.rp-sum__amount');
    if (instant) { amount.textContent = money(t.total); shown = t.total; } else animateAmount(amount, t.total);

    // Barra de descuento por volumen
    var maxMin = C.discounts[C.discounts.length - 1].min;
    body.querySelector('.rp-tier__bar i').style.width = Math.min(100, t.units / maxMin * 100) + '%';
    body.querySelectorAll('.rp-tier__marks span').forEach(function (m) {
      m.classList.toggle('is-hit', t.units >= parseInt(m.getAttribute('data-min'), 10));
    });
    var next = null;
    C.discounts.forEach(function (d) { if (!next && t.units < d.min) next = d; });
    var msg = body.querySelector('.rp-tier__msg');
    if (!t.units) msg.innerHTML = 'Sumá <b>' + C.discounts[0].min + ' ítems</b> y obtené un <b>' + C.discounts[0].pct + '% de descuento</b>.';
    else if (next) msg.innerHTML = (t.pct ? '¡Tenés <b>' + t.pct + '% off</b>! ' : '') + 'Sumá <b>' + (next.min - t.units) + '</b> más para llegar al <b>' + next.pct + '%</b>.';
    else msg.innerHTML = '¡Desbloqueaste el <b>descuento máximo del ' + t.pct + '%</b>!';

    if (t.pct > lastTier) celebrate(body, t.pct);
    lastTier = t.pct;
    body.querySelector('.rp-sum__go').disabled = !t.units;
  }

  /* Sello animado al alcanzar un nuevo nivel de descuento */
  function celebrate(body, pct) {
    var sum = body.querySelector('.rp-sum__panel');
    var stamp = document.createElement('div');
    stamp.className = 'rp-stamp';
    stamp.setAttribute('aria-hidden', 'true');
    stamp.innerHTML = '<b>−' + pct + '%</b><span>descuento desbloqueado</span>';
    sum.appendChild(stamp);
    setTimeout(function () { if (stamp.parentNode) stamp.parentNode.removeChild(stamp); }, 2200);
    var badge = body.querySelector('.rp-sum__badge');
    badge.classList.remove('is-pop');
    void badge.offsetWidth;
    badge.classList.add('is-pop');
  }

  /* ---------------------------------------------------------------- */
  /* Finalizar                                                          */
  /* ---------------------------------------------------------------- */
  function finalize(body, btn) {
    var err = body.querySelector('.rp-sum__error');
    err.hidden = true;
    btn.disabled = true;
    btn.classList.add('is-loading');
    btn.firstChild.textContent = 'Generando tu presupuesto… ';
    sendQuote(true)
      .then(function (res) {
        if (!res.ok) {
          if (res.error && /venció/.test(res.error)) {
            lead = null;
            try { sessionStorage.removeItem(KEY_LEAD); } catch (e) { /* nada */ }
          }
          throw new Error(res.error || 'No pudimos generar el presupuesto.');
        }
        thanks(body, res);
      })
      .catch(function (e) {
        err.textContent = e.message;
        err.hidden = false;
        if (!lead) setTimeout(render, 1600);
      })
      .then(function () {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        btn.firstChild.textContent = 'Finalizar presupuesto ';
      });
  }

  /* Si la sesión venció pero tenemos los datos, se vuelve a registrar sola y reintenta una vez */
  function sendQuote(retry) {
    return post('presupuesto', { lead: lead.id, token: lead.token, items: state.qty, segment: state.segment, currency: state.currency })
      .then(function (res) {
        if (res.ok || !retry || !res.error || !/venció/.test(res.error) || !lead.phone) return res;
        return post('lead', { name: lead.name, email: lead.email, phone: lead.phone, consent: 1, h: 'rp1', t: 9999 }).then(function (l) {
          if (!l.ok) return res;
          lead.id = l.lead;
          lead.token = l.token;
          try { sessionStorage.setItem(KEY_LEAD, JSON.stringify(lead)); } catch (e) { /* modo privado */ }
          return sendQuote(false);
        });
      });
  }

  function thanks(body, res) {
    var q = res.quote;
    var box = body.querySelector('.rp-thanks');
    var cur = state.currency;
    state.currency = q.currency;
    var total = money(q.total);
    state.currency = cur;
    box.innerHTML =
      '<div class="rp-thanks__card">' +
        '<div class="rp-thanks__check" aria-hidden="true"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg></div>' +
        '<span class="rp-thanks__num">Presupuesto enviado</span>' +
        '<h3 id="rp-thanks-title">¡Gracias, ' + esc(lead.name.split(' ')[0]) + '!</h3>' +
        '<p>Recibimos tu plan a medida por <strong>' + total + '</strong>' + (q.discount_pct ? ' con un <strong>' + q.discount_pct + '% de descuento</strong>' : '') + '.</p>' +
        '<p class="rp-thanks__mail">Te enviamos una copia en PDF a <strong>' + esc(lead.email || 'tu email') + '</strong>. Si no la ves en unos minutos, revisá Spam.</p>' +
        '<div class="rp-thanks__next"><strong>¿Querés seguir con la gestión?</strong><span>Escribinos por WhatsApp y lo ponemos en marcha.</span></div>' +
        '<a class="rp-btn rp-btn--wa rp-btn--big" href="' + esc(res.whatsapp) + '" target="_blank" rel="noopener">' +
          '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3a2.5 2.5 0 0 0-.8 1.9 4.4 4.4 0 0 0 .9 2.3 10 10 0 0 0 3.8 3.4c1.4.6 2 .7 2.7.6.4-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>' +
          'Continuar por WhatsApp</a>' +
        '<div class="rp-thanks__row">' +
          '<a class="rp-btn rp-btn--ghost" href="' + esc(res.pdf) + '" target="_blank" rel="noopener">Descargar PDF</a>' +
          '<button type="button" class="rp-btn rp-btn--ghost rp-thanks__back">Seguir editando</button>' +
        '</div>' +
      '</div>';
    box.hidden = false;
    requestAnimationFrame(function () { box.classList.add('is-open'); box.querySelector('.rp-btn--wa').focus({ preventScroll: true }); });
    box.querySelector('.rp-thanks__back').addEventListener('click', closeThanks);
  }
  function closeThanks() {
    var box = root.querySelector('.rp-thanks');
    box.classList.remove('is-open');
    setTimeout(function () { box.hidden = true; }, 350);
    var go = root.querySelector('.rp-sum__go');
    if (go) go.focus({ preventScroll: true });
  }

  function post(path, data) {
    return fetch(C.api + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'omit',
      body: JSON.stringify(data)
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Respuesta inválida del servidor.' }; });
    }).catch(function () {
      return { ok: false, error: 'No hay conexión. Revisá tu internet e intentá de nuevo.' };
    });
  }

  /* ---------------------------------------------------------------- */
  /* Disparadores: cualquier enlace a #calculadora y la URL directa      */
  /* ---------------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href$="#calculadora"]');
    if (!a) return;
    e.preventDefault();
    open();
  });
  window.addEventListener('hashchange', function () { if (location.hash === '#calculadora') open(); });
  if (location.hash === '#calculadora') open();
})();
