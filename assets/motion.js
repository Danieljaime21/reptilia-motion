/*!
 * Reptilia Motion — interacciones "cyber-noir"
 * Vanilla JS, sin dependencias. Todo lo que es movimiento respeta prefers-reduced-motion.
 */
(function () {
  'use strict';

  var html = document.documentElement;
  var motion = html.classList.contains('rp-motion');
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var isMobile = window.matchMedia('(max-width: 767px)').matches;

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function el(id) { return document.querySelector('.elementor-element-' + id); }
  function clamp(v, a, b) { return Math.min(b, Math.max(a, v)); }
  function lerp(a, b, t) { return a + (b - a) * t; }

  var vh = window.innerHeight;
  var scrollY = window.scrollY || 0;

  /* ------------------------------------------------------------------ */
  /* Texto dividido en palabras (conserva <b>, <span>, <br>)              */
  /* ------------------------------------------------------------------ */
  function splitWords(node) {
    if (!node || node.getAttribute('data-rp-split')) return [];
    node.setAttribute('data-rp-split', '1');
    var words = [];
    (function walk(parent) {
      Array.prototype.slice.call(parent.childNodes).forEach(function (child) {
        if (child.nodeType === 3) {
          var parts = child.textContent.split(/(\s+)/);
          var frag = document.createDocumentFragment();
          parts.forEach(function (p) {
            if (!p) return;
            if (/^\s+$/.test(p)) { frag.appendChild(document.createTextNode(' ')); return; }
            var w = document.createElement('span');
            w.className = 'rp-word';
            var i = document.createElement('span');
            i.className = 'rp-word-in';
            i.textContent = p;
            w.appendChild(i);
            frag.appendChild(w);
            words.push(w);
          });
          parent.replaceChild(frag, child);
        } else if (child.nodeType === 1 && child.tagName !== 'BR') {
          walk(child);
        }
      });
    })(node);
    words.forEach(function (w, i) { w.style.setProperty('--i', i); });
    return words;
  }

  /* ------------------------------------------------------------------ */
  /* Apariciones al hacer scroll                                          */
  /* ------------------------------------------------------------------ */
  var onReveal = [];
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) return;
      var t = e.target;
      t.classList.add('rp-in');
      onReveal.forEach(function (fn) { fn(t); });
      // Terminada la entrada, sin demora: así los efectos de hover responden al instante
      var d = parseInt(t.style.getPropertyValue('--d'), 10) || 0;
      setTimeout(function () { t.style.setProperty('--d', '0ms'); t.classList.add('rp-done'); }, d + 1400);
      io.unobserve(e.target);
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.04 }) : null;

  /**
   * type: up | fade | scale | left | clip | split
   * Si el elemento ya está en pantalla al cargar, se deja tal cual (sin parpadeo).
   */
  function reveal(target, type, delay, force) {
    if (!target || !motion || !io || target.classList.contains('rp-reveal')) return;
    if (!force && target.getBoundingClientRect().top < vh * 0.92) return;
    if (type === 'split') {
      var title = target.querySelector('.elementor-heading-title') || target;
      splitWords(title);
      target.classList.add('rp-split');
    }
    target.classList.add('rp-reveal', 'rp-' + (type || 'up'));
    target.style.setProperty('--d', (delay || 0) + 'ms');
    io.observe(target);
  }

  /* ------------------------------------------------------------------ */
  /* Elementos globales: progreso, grano, cursor                          */
  /* ------------------------------------------------------------------ */
  var progress = document.createElement('div');
  progress.className = 'rp-progress';
  progress.setAttribute('aria-hidden', 'true');
  document.body.appendChild(progress);

  if (motion) {
    var grain = document.createElement('div');
    grain.className = 'rp-grain';
    grain.setAttribute('aria-hidden', 'true');
    document.body.appendChild(grain);
  }

  var cursor = null, cursorGlow = null;
  var mouse = { x: window.innerWidth / 2, y: vh / 2, nx: 0, ny: 0 };
  if (motion && finePointer && !isMobile) {
    cursor = document.createElement('div');
    cursor.className = 'rp-cursor';
    cursorGlow = document.createElement('div');
    cursorGlow.className = 'rp-cursor-glow';
    cursor.setAttribute('aria-hidden', 'true');
    cursorGlow.setAttribute('aria-hidden', 'true');
    document.body.appendChild(cursorGlow);
    document.body.appendChild(cursor);

    var cx = mouse.x, cy = mouse.y, gx = mouse.x, gy = mouse.y, cursorRaf = 0;
    var cursorTick = function () {
      cx = lerp(cx, mouse.x, 0.22);
      cy = lerp(cy, mouse.y, 0.22);
      gx = lerp(gx, mouse.x, 0.08);
      gy = lerp(gy, mouse.y, 0.08);
      cursor.style.transform = 'translate3d(' + cx + 'px,' + cy + 'px,0)';
      cursorGlow.style.transform = 'translate3d(' + gx + 'px,' + gy + 'px,0)';
      if (Math.abs(gx - mouse.x) + Math.abs(gy - mouse.y) > 0.4) {
        cursorRaf = requestAnimationFrame(cursorTick);
      } else {
        cursorRaf = 0;
      }
    };
    window.addEventListener('mousemove', function (e) {
      mouse.x = e.clientX;
      mouse.y = e.clientY;
      html.classList.add('rp-cursor-on');
      if (!cursorRaf) cursorRaf = requestAnimationFrame(cursorTick);
    }, { passive: true });
    document.addEventListener('mouseleave', function () { html.classList.remove('rp-cursor-on'); });
    document.addEventListener('mousedown', function () { html.classList.add('rp-cursor-down'); });
    document.addEventListener('mouseup', function () { html.classList.remove('rp-cursor-down'); });
    document.addEventListener('mouseover', function (e) {
      var t = e.target.closest && e.target.closest('a, button, input, textarea, select, [role="button"], .rp-card, .rp-marquee__item');
      html.classList.toggle('rp-cursor-hover', !!t);
    });
  } else {
    window.addEventListener('mousemove', function (e) {
      mouse.x = e.clientX;
      mouse.y = e.clientY;
    }, { passive: true });
  }

  /* Botones magnéticos */
  function magnetic(node, strength) {
    if (!node || !motion || !finePointer) return;
    node.classList.add('rp-magnetic');
    var host = node.parentElement || node;
    host.addEventListener('mousemove', function (e) {
      var r = node.getBoundingClientRect();
      var dx = e.clientX - (r.left + r.width / 2);
      var dy = e.clientY - (r.top + r.height / 2);
      node.style.transform = 'translate3d(' + dx * strength + 'px,' + dy * strength + 'px,0)';
    });
    host.addEventListener('mouseleave', function () { node.style.transform = ''; });
  }

  /* ------------------------------------------------------------------ */
  /* PORTADA                                                               */
  /* ------------------------------------------------------------------ */
  var hero = el('e2b531f');
  var heroState = null;

  if (hero) {
    // Capa de imagen propia (permite zoom y parallax sin mover el texto).
    // Se lee la imagen ANTES de agregar .rp-hero, que anula el fondo original.
    var heroCS = window.getComputedStyle(hero);
    var bg = heroCS.backgroundImage;
    var bgPos = heroCS.backgroundPosition;
    hero.classList.add('rp-hero');
    var media = document.createElement('div');
    media.className = 'rp-hero-media';
    media.setAttribute('aria-hidden', 'true');
    if (bg && bg !== 'none') {
      media.style.backgroundImage = bg;
      if (bgPos) media.style.backgroundPosition = bgPos;
    } else {
      hero.classList.remove('rp-hero'); // sin imagen detectada: no tocar el fondo original
    }
    var glow = document.createElement('div');
    glow.className = 'rp-hero-glow';
    var grid = document.createElement('div');
    grid.className = 'rp-hero-grid';
    var fade = document.createElement('div');
    fade.className = 'rp-hero-fade';
    [glow, grid, fade].forEach(function (n) { n.setAttribute('aria-hidden', 'true'); });
    hero.insertBefore(fade, hero.firstChild);
    hero.insertBefore(grid, hero.firstChild);
    hero.insertBefore(glow, hero.firstChild);
    hero.insertBefore(media, hero.firstChild);

    var canvas = null, ctx = null, particles = [];
    if (motion) {
      canvas = document.createElement('canvas');
      canvas.className = 'rp-hero-particles';
      canvas.setAttribute('aria-hidden', 'true');
      hero.insertBefore(canvas, fade);
      ctx = canvas.getContext('2d');
    }

    // Indicador de scroll
    var next = hero.nextElementSibling;
    var cue = document.createElement('a');
    cue.className = 'rp-scroll-cue';
    cue.href = '#';
    cue.setAttribute('aria-label', 'Bajar a la siguiente sección');
    cue.innerHTML = '<span>Scroll</span><i></i>';
    cue.addEventListener('click', function (e) {
      e.preventDefault();
      if (next) window.scrollTo({ top: next.getBoundingClientRect().top + window.scrollY - 10, behavior: 'smooth' });
    });
    hero.appendChild(cue);

    var eyebrow = el('86551da');
    var eyebrowP = eyebrow && eyebrow.querySelector('p');
    var h1wrap = el('c3c1a4a');
    var h1 = h1wrap && h1wrap.querySelector('.elementor-heading-title');

    if (motion && h1) {
      var words = splitWords(h1);
      h1wrap.classList.add('rp-split');
      // Resaltar la promesa: "SIN SOBREPRECIOS."
      words.forEach(function (w) {
        if (/^(sin|sobreprecios\.?)$/i.test(w.textContent.trim())) w.classList.add('rp-hl');
      });
    }
    if (motion && eyebrow) eyebrow.classList.add('rp-hero-wait');

    heroState = { media: media, glow: glow, canvas: canvas, ctx: ctx, particles: particles, visible: true, mx: 0, my: 0 };

    // Partículas: "polvo digital" violeta
    if (canvas) {
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      var sizeCanvas = function () {
        var r = hero.getBoundingClientRect();
        canvas.width = Math.round(r.width * dpr);
        canvas.height = Math.round(r.height * dpr);
      };
      sizeCanvas();
      var count = isMobile ? 34 : 80;
      for (var i = 0; i < count; i++) {
        particles.push({
          x: Math.random(),
          y: Math.random(),
          r: Math.random() * 1.6 + 0.4,
          v: Math.random() * 0.00035 + 0.00012,
          drift: (Math.random() - 0.5) * 0.00018,
          tw: Math.random() * Math.PI * 2,
          depth: Math.random() * 0.8 + 0.2,
          hue: Math.random() < 0.7 ? '201,167,255' : '138,43,226'
        });
      }
      window.addEventListener('resize', sizeCanvas);
    }

    // Intro: espera a que se vaya la cortina (si la hay)
    var startHero = function () {
      if (eyebrow) {
        eyebrow.classList.remove('rp-hero-wait');
        if (eyebrowP && motion) scramble(eyebrowP, 1300);
      }
      if (h1wrap) {
        h1wrap.style.setProperty('--d', '250ms');
        setTimeout(function () { h1wrap.classList.add('rp-in'); }, 30);
      }
      setTimeout(function () { cue.classList.add('is-in'); }, 1400);
    };
    heroState.start = startHero;
  }

  /* Efecto "decodificado" para la etiqueta superior */
  function scramble(node, duration) {
    var finalText = node.textContent;
    var glyphs = 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ0123456789#%&/<>_=+';
    var width = node.getBoundingClientRect().width;
    node.style.minWidth = Math.ceil(width) + 'px';
    node.setAttribute('aria-label', finalText);
    var start = performance.now();
    (function frame(now) {
      var p = clamp((now - start) / duration, 0, 1);
      var settled = Math.floor(p * finalText.length);
      var out = '';
      for (var i = 0; i < finalText.length; i++) {
        var c = finalText.charAt(i);
        out += (i < settled || c === ' ' || c === ',') ? c : glyphs.charAt((Math.random() * glyphs.length) | 0);
      }
      node.textContent = out;
      if (p < 1) requestAnimationFrame(frame);
      else { node.textContent = finalText; node.style.minWidth = ''; }
    })(start);
  }

  /* SERVICIOS: tarjetas */
  var cards = $$('.elementor-element-6981008 > .elementor-widget-icon-box');
  if (cards.length) el('6981008').classList.add('rp-cards');
  cards.forEach(function (card, i) {
    card.classList.add('rp-card');
    var idx = document.createElement('span');
    idx.className = 'rp-idx';
    idx.setAttribute('aria-hidden', 'true');
    idx.textContent = ('0' + (i + 1)).slice(-2);
    card.insertBefore(idx, card.firstChild);
    card.addEventListener('pointermove', function (e) {
      var r = card.getBoundingClientRect();
      card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      card.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
    reveal(card, 'scale', i * 110);
  });

  /* POR QUÉ ELEGIR REPTILIA */
  reveal(el('7d26fa8'), 'clip', 0);
  reveal(el('88662d8'), 'split', 0);
  var whyTitle = el('88662d8');
  if (whyTitle) {
    $$('.rp-word', whyTitle).forEach(function (w) {
      if (/reptilia/i.test(w.textContent)) w.classList.add('rp-hl');
    });
  }
  reveal(el('1e999b0'), 'up', 150);
  reveal(el('8a430c3'), 'fade', 200);

  // Contadores: "+3 años…" / "23 clientes…"
  ['2525077', '602bb96'].forEach(function (id, i) {
    var box = el(id);
    if (!box) return;
    box.classList.add('rp-stat');
    var span = box.querySelector('.elementor-icon-box-title span') || box.querySelector('.elementor-icon-box-title');
    if (span) {
      var m = span.textContent.match(/^(\s*)(\+?)(\d+)(.*)$/);
      if (m) {
        var to = parseInt(m[3], 10);
        span.innerHTML = '';
        var num = document.createElement('span');
        num.className = 'rp-num';
        num.textContent = m[2] + to;
        num.setAttribute('data-to', to);
        num.setAttribute('data-prefix', m[2]);
        span.appendChild(num);
        span.appendChild(document.createTextNode(m[4].trim()));
      }
    }
    reveal(box, 'up', 250 + i * 140);
  });

  onReveal.push(function (target) {
    $$('.rp-num', target).forEach(function (n) {
      var to = parseInt(n.getAttribute('data-to'), 10) || 0;
      var prefix = n.getAttribute('data-prefix') || '';
      var start = performance.now();
      var dur = 1800;
      (function step(now) {
        var p = clamp((now - start) / dur, 0, 1);
        var eased = 1 - Math.pow(1 - p, 4);
        n.textContent = prefix + Math.round(to * eased);
        if (p < 1) requestAnimationFrame(step);
      })(start);
    });
  });

  /* Recorta el margen vacío de un logo (PNG cuadrado con mucho aire alrededor).
     Devuelve la imagen recortada y su proporción, o null si no se pudo leer. */
  var trimCache = {};
  function trimLogo(src) {
    if (trimCache[src]) return trimCache[src];
    trimCache[src] = new Promise(function (resolve) {
      var im = new Image();
      im.onload = function () {
        try {
          var w = im.naturalWidth, h = im.naturalHeight;
          var cv = document.createElement('canvas');
          cv.width = w;
          cv.height = h;
          var cx = cv.getContext('2d');
          cx.drawImage(im, 0, 0);
          var d = cx.getImageData(0, 0, w, h).data;
          var minX = w, minY = h, maxX = -1, maxY = -1;
          for (var y = 0; y < h; y++) {
            for (var x = 0; x < w; x++) {
              var i = (y * w + x) * 4;
              // "Tinta" = píxel visible y no casi negro (algunos PNG traen fondo negro)
              if (d[i + 3] > 24 && d[i] + d[i + 1] + d[i + 2] > 90) {
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
              }
            }
          }
          if (maxX < 0) { resolve(null); return; }
          var pad = 3;
          minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
          maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
          var out = document.createElement('canvas');
          out.width = maxX - minX + 1;
          out.height = maxY - minY + 1;
          out.getContext('2d').drawImage(cv, minX, minY, out.width, out.height, 0, 0, out.width, out.height);
          resolve({ url: out.toDataURL('image/png'), ratio: out.width / out.height });
        } catch (e) {
          resolve(null); // p. ej. imagen servida desde otro dominio
        }
      };
      im.onerror = function () { resolve(null); };
      im.src = src;
    });
    return trimCache[src];
  }

  /* CLIENTES: cinta infinita de logos */
  var logoGrid = el('e1b576b');
  if (logoGrid) {
    var imgs = $$('img', logoGrid);
    if (imgs.length) {
      var buildRow = function (list, reverse, speed) {
        var wrap = document.createElement('div');
        wrap.className = 'rp-marquee' + (reverse ? ' rp-marquee--reverse' : '');
        wrap.style.setProperty('--rp-speed', speed + 's');
        var track = document.createElement('div');
        track.className = 'rp-marquee__track';
        for (var copy = 0; copy < 2; copy++) {
          list.forEach(function (img) {
            var item = document.createElement('div');
            item.className = 'rp-marquee__item';
            var c = document.createElement('img');
            var src = img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || img.currentSrc || img.src;
            c.alt = copy === 0 ? (img.alt || '') : '';
            c.decoding = 'async';
            c.className = 'rp-pending';
            (function (node, url) {
              trimLogo(url).then(function (res) {
                if (res) {
                  node.src = res.url;
                  // Altura según la forma: logos anchos más bajos, logos cuadrados más altos
                  var hgt = res.ratio > 4 ? 30 : res.ratio > 2.6 ? 38 : res.ratio > 1.5 ? 48 : 62;
                  node.style.setProperty('--h', hgt + 'px');
                } else {
                  node.src = url;
                  node.classList.add('rp-untrimmed');
                }
                node.classList.remove('rp-pending');
              });
            })(c, src);
            if (copy > 0) item.setAttribute('aria-hidden', 'true');
            item.appendChild(c);
            track.appendChild(item);
          });
        }
        wrap.appendChild(track);
        return wrap;
      };
      var half = Math.ceil(imgs.length / 2);
      var rowA = buildRow(imgs.slice(0, half), false, 38);
      var rowB = buildRow(imgs.slice(half), true, 44);
      // Un solo envoltorio: así no hereda el espacio entre hijos del contenedor de Elementor
      var rows = document.createElement('div');
      rows.className = 'rp-marquees';
      rows.appendChild(rowA);
      rows.appendChild(rowB);
      logoGrid.parentNode.insertBefore(rows, logoGrid);
      logoGrid.classList.add('rp-marquee-src');
      var slideshow = el('24d2041');
      if (slideshow) slideshow.classList.add('rp-marquee-src');
      reveal(rowA, 'fade', 0);
      reveal(rowB, 'fade', 150);
    }
  }
  reveal(el('a2bf3c1'), 'split', 0);

  /* PACKS DE SERVICIOS */
  var packs = $('.rp-packs');
  if (packs) {
    reveal($('.rp-packs__eyebrow', packs), 'up', 0);
    reveal($('.rp-packs__title', packs), 'split', 80);
    var packTitle = $('.rp-packs__title', packs);
    if (packTitle) {
      $$('.rp-word', packTitle).forEach(function (w) {
        if (/crecer/i.test(w.textContent)) w.classList.add('rp-hl');
      });
    }
    reveal($('.rp-packs__intro', packs), 'up', 200);
    $$('.rp-pack', packs).forEach(function (card, i) {
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        card.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
      reveal(card, 'up', 150 + i * 140);
    });
    reveal($('.rp-extras', packs), 'up', 0);
    var custom = $('.rp-custom', packs);
    if (custom) {
      custom.addEventListener('pointermove', function (e) {
        var r = custom.getBoundingClientRect();
        custom.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        custom.style.setProperty('--my', (e.clientY - r.top) + 'px');
      });
      reveal(custom, 'scale', 120);
      magnetic($('.rp-custom__cta .elementor-button', custom), 0.25);
    }
  }

  /* CIERRE */
  var cta = el('437f3c13');
  if (cta) {
    var orb = document.createElement('div');
    orb.className = 'rp-cta-orb';
    orb.setAttribute('aria-hidden', 'true');
    cta.insertBefore(orb, cta.firstChild);
  }
  reveal(el('2e01f5e7'), 'split', 0);
  reveal(el('765ceeaa'), 'up', 350);
  var ctaBtn = $('.elementor-element-765ceeaa .elementor-button-wrapper');
  magnetic(ctaBtn, 0.35);
  magnetic($('#masthead .ast-custom-button'), 0.3);
  var pen = el('1f6a7b5');

  /* FOOTER */
  ['92e437e', '350c62e', 'ba1d580', '78ef72e'].forEach(function (id, i) {
    reveal(el(id), 'up', i * 110);
  });

  /* OTRAS PÁGINAS hechas con Elementor: aparición genérica */
  if (!hero) {
    $$('.elementor .elementor-widget').forEach(function (w) {
      if (w.closest('#masthead') || w.closest('.rp-reveal')) return;
      var siblings = w.parentElement ? Array.prototype.indexOf.call(w.parentElement.children, w) : 0;
      var type = w.classList.contains('elementor-widget-heading') ? 'split' : 'up';
      reveal(w, type, Math.min(siblings, 5) * 90);
    });
  }

  /* ------------------------------------------------------------------ */
  /* Bucle de scroll (un solo requestAnimationFrame)                      */
  /* ------------------------------------------------------------------ */
  var transparentHeader = document.body.classList.contains('ast-theme-transparent-header');
  var lastY = scrollY;
  var ticking = false;

  function onScroll() {
    scrollY = window.scrollY || 0;
    var max = document.documentElement.scrollHeight - vh;
    progress.style.transform = 'scaleX(' + (max > 0 ? clamp(scrollY / max, 0, 1) : 0) + ')';
    html.classList.toggle('rp-scrolled', scrollY > 40);

    if (transparentHeader) {
      if (scrollY > lastY + 6 && scrollY > vh * 0.7) html.classList.add('rp-header-hidden');
      else if (scrollY < lastY - 6 || scrollY < 120) html.classList.remove('rp-header-hidden');
    }
    lastY = scrollY;

    if (motion) {
      // El texto del hero se aleja con profundidad
      if (hero && scrollY < vh * 1.2) {
        var k = clamp(scrollY / (vh * 0.85), 0, 1);
        [el('86551da'), el('c3c1a4a')].forEach(function (n, i) {
          if (!n) return;
          n.style.transform = 'translate3d(0,' + (scrollY * (0.22 + i * 0.08)) + 'px,0)';
          n.style.opacity = String(1 - k * 1.1);
        });
      }
      // La lapicera "despega" al pasar por el cierre
      if (pen) {
        var r = pen.getBoundingClientRect();
        if (r.top < vh && r.bottom > 0) {
          var t = clamp(1 - (r.top + r.height / 2) / (vh + r.height / 2), 0, 1);
          var img = pen.querySelector('img');
          if (img) img.style.transform = 'translate3d(0,' + ((0.5 - t) * 120) + 'px,0) rotate(' + ((0.5 - t) * -6) + 'deg)';
        }
      }
      var whyImg = el('7d26fa8');
      if (whyImg) {
        var wr = whyImg.getBoundingClientRect();
        if (wr.top < vh && wr.bottom > 0) {
          var wt = (wr.top + wr.height / 2) / vh - 0.5;
          var wi = whyImg.querySelector('img');
          if (wi) wi.style.transform = 'translate3d(0,' + (wt * 60) + 'px,0)';
        }
      }
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  window.addEventListener('resize', function () { vh = window.innerHeight; onScroll(); });
  onScroll();

  /* ------------------------------------------------------------------ */
  /* Bucle del hero: parallax con mouse + partículas (solo si se ve)      */
  /* ------------------------------------------------------------------ */
  if (heroState && motion) {
    var heroRaf = 0;
    var hio = new IntersectionObserver(function (entries) {
      heroState.visible = entries[0].isIntersecting;
      if (heroState.visible && !heroRaf) heroRaf = requestAnimationFrame(heroLoop);
    });
    hio.observe(hero);

    var introDone = false;
    setTimeout(function () { introDone = true; }, 2600);

    var heroLoop = function (now) {
      heroRaf = 0;
      if (!heroState.visible || document.hidden) return;
      var w = window.innerWidth;
      var tx = finePointer ? (mouse.x / w - 0.5) : 0;
      var ty = finePointer ? (mouse.y / vh - 0.5) : 0;
      heroState.mx = lerp(heroState.mx, tx, 0.05);
      heroState.my = lerp(heroState.my, ty, 0.05);

      if (introDone) {
        heroState.media.style.animation = 'none';
        heroState.media.style.transform =
          'translate3d(' + (heroState.mx * -18) + 'px,' + (scrollY * 0.32 + heroState.my * -12) + 'px,0) scale(' + (1.04 + Math.min(scrollY / vh, 1) * 0.08) + ')';
      }
      heroState.glow.style.transform = 'translate3d(' + (heroState.mx * -18) + 'px,' + (scrollY * 0.32 + heroState.my * -12) + 'px,0)';

      var c = heroState.canvas, ctx2 = heroState.ctx;
      if (c && ctx2) {
        var cw = c.width, ch = c.height;
        ctx2.clearRect(0, 0, cw, ch);
        for (var i = 0; i < heroState.particles.length; i++) {
          var p = heroState.particles[i];
          p.y -= p.v;
          p.x += p.drift;
          p.tw += 0.02;
          if (p.y < -0.02) { p.y = 1.02; p.x = Math.random(); }
          if (p.x < -0.02) p.x = 1.02;
          if (p.x > 1.02) p.x = -0.02;
          var px = p.x * cw + heroState.mx * -40 * p.depth * dpr;
          var py = p.y * ch + heroState.my * -30 * p.depth * dpr;
          var a = (0.25 + Math.sin(p.tw) * 0.2 + 0.2) * p.depth;
          ctx2.beginPath();
          ctx2.fillStyle = 'rgba(' + p.hue + ',' + (a * 0.25).toFixed(3) + ')';
          ctx2.arc(px, py, p.r * 3.2 * dpr, 0, Math.PI * 2);
          ctx2.fill();
          ctx2.beginPath();
          ctx2.fillStyle = 'rgba(' + p.hue + ',' + a.toFixed(3) + ')';
          ctx2.arc(px, py, p.r * dpr, 0, Math.PI * 2);
          ctx2.fill();
        }
      }
      heroRaf = requestAnimationFrame(heroLoop);
    };
    heroRaf = requestAnimationFrame(heroLoop);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && heroState.visible && !heroRaf) heroRaf = requestAnimationFrame(heroLoop);
    });
  }

  /* ------------------------------------------------------------------ */
  /* Arranque: cortina de intro → coreografía del hero                    */
  /* ------------------------------------------------------------------ */
  html.classList.add('rp-booted');
  var curtain = $('.rp-curtain');
  var curtainActive = curtain && motion && !html.classList.contains('rp-seen') && !html.classList.contains('rp-failed');

  function go() {
    if (heroState && heroState.start) heroState.start();
  }

  if (curtainActive) {
    try { window.sessionStorage.setItem('rpSeen', '1'); } catch (e) { /* modo privado */ }
    var begun = performance.now();
    var left = false;
    var leave = function () {
      if (left) return;
      left = true;
      var wait = Math.max(0, 1150 - (performance.now() - begun));
      setTimeout(function () {
        curtain.classList.add('is-out');
        setTimeout(go, 380);
        setTimeout(function () { if (curtain.parentNode) curtain.parentNode.removeChild(curtain); }, 1100);
      }, wait);
    };
    if (document.readyState === 'complete') leave();
    else {
      window.addEventListener('load', leave, { once: true });
      setTimeout(leave, 2000); // nunca más de 2 s aunque la página siga cargando
    }
  } else {
    if (curtain && curtain.parentNode) curtain.parentNode.removeChild(curtain);
    requestAnimationFrame(go);
  }
})();
