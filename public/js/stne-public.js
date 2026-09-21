/**
 * Sticky Notes Everywhere — overlay.
 *
 * Reads window.stneConfig (inlined by Plugin::assets), talks to REST
 * sticky-notes-everywhere/v1, and draws notes on #sne-app.
 *
 * Note object keys: id, user, title, content, color, scope, path, heading,
 * x, y, width, height, z, minimized, created, updated.
 *
 * No jQuery. No remote assets. Hide preference is localStorage only.
 */
(() => {
  const cfg = window.stneConfig || {};
  if (!cfg.root) {
    return;
  }

  const i18n = cfg.i18n || {};
  const colors = cfg.colors || { yellow: '#fff3a3' };
  const svg = {
    plus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5h2v14h-2zM5 11h14v2H5z"/></svg>',
    list: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>',
    eye: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 4.5 10.5 7-1 2.5-5 7-10.5 7S2.5 14.5 1.5 12C2.5 9.5 6.5 5 12 5zm0 3.5A3.5 3.5 0 1 0 12 15a3.5 3.5 0 0 0 0-7z"/></svg>',
    shut: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4.3 4.3 3 21 19.7 19.7 21l-3.2-3.2C14.9 18.5 13.5 19 12 19 6.5 19 2.5 14.5 1.5 12c.5-1.3 2-3.6 4.3-5.4L3 4.3zM12 7c5.5 0 9.5 4.5 10.5 7-.4 1-1.4 2.7-3 4.2L8.2 6.9C9.4 7 10.7 7 12 7z"/></svg>',
    pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 3.5 20.5 9.5 13 17l-1.8-1.8-5.7 5.7-1.4-1.4 5.7-5.7L8 12z"/></svg>',
    min: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11h14v2H5z"/></svg>',
    max: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7zm2 2v6h6V9z"/></svg>',
    palette: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0 0 18h1.2a2.2 2.2 0 0 0 1.9-3.4 2.2 2.2 0 0 1 1.9-3.4H18a3 3 0 0 0 0-6h-.4A9 9 0 0 0 12 3zm-4.5 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/></svg>',
    trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h5v2H3V5h5zm1 6h2v9h-2zm4 0h2v9h-2zM7 9h2v9H7z"/></svg>',
    close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12 19 6.4 17.6 5 12 10.6z"/></svg>',
  };

  const state = {
    notes: new Map(),
    data: new Map(),
    hidden: window.localStorage.getItem('stne-hidden') === '1',
    seen: window.localStorage.getItem('stne-hint') === '1',
    timers: new Map(),
    pending: new Map(),
    stack: 1,
    drawer: false,
  };

  /**
   * Merge a REST note into state.data so later renders share one object.
   */
  function remember(note) {
    const current = state.data.get(note.id);
    if (current) {
      Object.assign(current, note);
      return current;
    }
    state.data.set(note.id, note);
    return note;
  }

  const app = document.getElementById('sne-app');
  if (!app) {
    return;
  }

  /**
   * Translated string from stneConfig.i18n.
   */
  function t(key, fallback) {
    return i18n[key] || fallback || key;
  }

  /**
   * Locale direction: PHP is_rtl() first, then html/body dir.
   */
  function rtl() {
    if (typeof cfg.rtl === 'boolean') {
      return cfg.rtl;
    }
    const dir = document.documentElement.getAttribute('dir')
      || document.body.getAttribute('dir')
      || window.getComputedStyle(document.body).direction;
    return dir === 'rtl' || document.body.classList.contains('rtl');
  }

  /**
   * Path stored on a page-scoped note. wp-admin keeps the query string.
   */
  function path() {
    const value = (window.location.pathname.replace(/\/+$/, '') || '/');
    if (cfg.admin) {
      return value + window.location.search;
    }
    return value;
  }

  /**
   * Document title without “ — Sitename”.
   */
  function heading() {
    return (document.title || '').replace(/\s+[—–|-]\s+.*$/, '').trim();
  }

  /**
   * Height of #wpadminbar so notes do not sit under it.
   */
  function bar() {
    const el = document.getElementById('wpadminbar');
    return el ? el.offsetHeight : 8;
  }

  /**
   * Keep a note inside the viewport.
   */
  function clamp(x, y, w, h) {
    const miny = bar() + 10;
    const maxx = Math.max(8, window.innerWidth - w - 8);
    const maxy = Math.max(miny, window.innerHeight - 48);
    return {
      x: Math.max(8, Math.min(x, maxx)),
      y: Math.max(miny, Math.min(y, maxy)),
    };
  }

  /**
   * Tiny paper tilt from the note id.
   */
  function tilt(id) {
    return `${((id % 7) - 3) * 0.7}deg`;
  }

  /**
   * First position for a new note. LTR: start edge. RTL: end edge. Skip the admin menu.
   */
  function spawn(w, h, offset) {
    const y = bar() + 80 + offset;
    const menu = cfg.admin ? document.getElementById('adminmenuwrap') : null;
    const width = menu ? menu.getBoundingClientRect().width : 0;
    const gap = cfg.admin ? width + 24 : 96;
    const x = rtl()
      ? window.innerWidth - w - gap - offset
      : gap + offset;
    return clamp(x, y, w, h);
  }

  /**
   * REST helper: cookie auth + X-WP-Nonce.
   */
  async function request(route, options = {}) {
    const res = await fetch(cfg.root + route, {
      credentials: 'same-origin',
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
        ...(options.headers || {}),
      },
    });

    if (res.status === 204) {
      return null;
    }

    let body = null;
    try {
      body = await res.json();
    } catch (err) {
      body = null;
    }

    if (!res.ok) {
      const message = (body && (body.message || body.data?.message)) || t('error');
      throw new Error(message);
    }

    return body;
  }

  let ticker = 0;

  /**
   * Short error/status banner.
   */
  function toast(message) {
    let el = app.querySelector('.sne-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'sne-toast';
      el.setAttribute('role', 'status');
      app.appendChild(el);
    }
    el.textContent = message;
    el.hidden = false;
    window.clearTimeout(ticker);
    ticker = window.setTimeout(() => {
      el.hidden = true;
    }, 2800);
  }

  /**
   * Write x/y/w/h/rotation CSS variables onto a note element.
   */
  function place(el, note) {
    const w = note.minimized ? Math.min(note.width, 220) : note.width;
    const h = note.minimized ? 42 : note.height;
    const pos = clamp(note.x, note.y, w, h);
    el.style.setProperty('--sne-x', `${pos.x}px`);
    el.style.setProperty('--sne-y', `${pos.y}px`);
    el.style.setProperty('--sne-w', `${note.width}px`);
    el.style.setProperty('--sne-h', `${note.height}px`);
    el.style.setProperty('--sne-rot', tilt(note.id));
    el.style.zIndex = String(99980 + note.z);
  }

  /**
   * Raise stacking order and persist it.
   */
  function raise(note) {
    state.stack += 1;
    note.z = state.stack;
    const el = state.notes.get(note.id);
    if (el) {
      el.style.zIndex = String(99980 + note.z);
    }
    save(note, { z: note.z }, 0);
  }

  /**
   * Debounced POST /notes/{id}. Pending patches merge so typing does not drop fields.
   */
  function save(note, patch, delay = 400) {
    Object.assign(note, patch);
    const pending = Object.assign({}, state.pending.get(note.id) || {}, patch);
    state.pending.set(note.id, pending);
    const prev = state.timers.get(note.id);
    if (prev) {
      window.clearTimeout(prev);
    }
    const timer = window.setTimeout(async () => {
      state.timers.delete(note.id);
      const body = state.pending.get(note.id);
      state.pending.delete(note.id);
      if (!body) {
        return;
      }
      try {
        await request(`notes/${note.id}`, {
          method: 'POST',
          body: JSON.stringify(body),
        });
      } catch (err) {
        toast(err.message || t('error'));
      }
    }, delay);
    state.timers.set(note.id, timer);
  }

  /**
   * Close every color palette except the one just opened.
   */
  function palettes(except) {
    app.querySelectorAll('.sne-note__palette.is-open').forEach((el) => {
      if (el !== except) {
        el.classList.remove('is-open');
      }
    });
  }

  /**
   * Create or refresh a note DOM node from a data object.
   */
  function render(note) {
    let el = state.notes.get(note.id);
    const fresh = !el;
    if (!el) {
      el = document.createElement('article');
      el.className = 'sne-note';
      el.innerHTML = `
        <header class="sne-note__bar">
          <button type="button" class="sne-icon sne-note__pin" aria-pressed="false">${svg.pin}</button>
          <input class="sne-note__title" maxlength="190">
          <div class="sne-note__actions">
            <button type="button" class="sne-icon sne-note__min">${svg.min}</button>
            <button type="button" class="sne-icon sne-note__color">${svg.palette}</button>
            <button type="button" class="sne-icon sne-icon--danger sne-note__del">${svg.trash}</button>
          </div>
        </header>
        <textarea class="sne-note__body"></textarea>
        <div class="sne-note__palette" role="listbox"></div>
        <div class="sne-note__resize"></div>
      `;

      const palette = el.querySelector('.sne-note__palette');
      Object.keys(colors).forEach((slug) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'sne-dot';
        dot.dataset.color = slug;
        dot.title = slug;
        dot.setAttribute('role', 'option');
        palette.appendChild(dot);
      });

      bind(el, note);
      app.appendChild(el);
      state.notes.set(note.id, el);
    }

    el.dataset.id = String(note.id);
    el.id = `sne-${note.id}`;
    el.dataset.color = note.color;
    el.classList.toggle('is-min', !!note.minimized);
    place(el, note);

    const title = el.querySelector('.sne-note__title');
    const body = el.querySelector('.sne-note__body');
    const pin = el.querySelector('.sne-note__pin');
    const minify = el.querySelector('.sne-note__min');
    title.value = note.title || '';
    title.placeholder = t('untitled');
    title.setAttribute('aria-label', t('title'));
    body.value = note.content || '';
    body.placeholder = t('body');
    body.setAttribute('aria-label', t('body'));
    pin.setAttribute('aria-pressed', note.scope === 'global' ? 'true' : 'false');
    pin.classList.toggle('is-on', note.scope === 'global');
    pin.title = note.scope === 'global' ? t('unpin') : t('pin');
    minify.innerHTML = note.minimized ? svg.max : svg.min;
    minify.title = note.minimized ? t('restore') : t('minimize');
    pin.setAttribute('aria-label', pin.title);
    minify.setAttribute('aria-label', minify.title);
    el.querySelector('.sne-note__color').title = t('color');
    el.querySelector('.sne-note__color').setAttribute('aria-label', t('color'));
    el.querySelector('.sne-note__del').title = t('erase');
    el.querySelector('.sne-note__del').setAttribute('aria-label', t('erase'));

    el.querySelectorAll('.sne-dot').forEach((dot) => {
      dot.classList.toggle('is-on', dot.dataset.color === note.color);
    });

    if (fresh && !state.seen) {
      const hint = document.createElement('div');
      hint.className = 'sne-hint';
      hint.textContent = t('drag');
      el.appendChild(hint);
      window.setTimeout(() => hint.remove(), 3500);
      state.seen = true;
      window.localStorage.setItem('stne-hint', '1');
    }

    if (note.z > state.stack) {
      state.stack = note.z;
    }

    return el;
  }

  /**
   * Wire title/body/pin/min/color/delete/drag/resize once per element.
   */
  function bind(el, seed) {
    const title = el.querySelector('.sne-note__title');
    const body = el.querySelector('.sne-note__body');
    const handle = el.querySelector('.sne-note__bar');
    const palette = el.querySelector('.sne-note__palette');
    const grip = el.querySelector('.sne-note__resize');

    const item = () => {
      const id = Number(el.dataset.id);
      return find(id) || seed;
    };

    title.addEventListener('input', () => {
      const note = item();
      save(note, { title: title.value });
    });

    body.addEventListener('input', () => {
      const note = item();
      save(note, { content: body.value });
    });

    el.querySelector('.sne-note__pin').addEventListener('click', (event) => {
      event.stopPropagation();
      const note = item();
      const scope = note.scope === 'global' ? 'page' : 'global';
      note.scope = scope;
      if (scope === 'page') {
        note.path = path();
        note.heading = heading();
      }
      render(note);
      save(note, {
        scope,
        path: note.path,
        heading: note.heading,
      }, 0);
    });

    el.querySelector('.sne-note__min').addEventListener('click', (event) => {
      event.stopPropagation();
      const note = item();
      note.minimized = !note.minimized;
      render(note);
      save(note, { minimized: note.minimized }, 0);
    });

    el.querySelector('.sne-note__color').addEventListener('click', (event) => {
      event.stopPropagation();
      const shown = !palette.classList.contains('is-open');
      palettes(palette);
      palette.classList.toggle('is-open', shown);
    });

    palette.addEventListener('click', (event) => {
      const dot = event.target.closest('.sne-dot');
      if (!dot) {
        return;
      }
      const note = item();
      note.color = dot.dataset.color;
      palette.classList.remove('is-open');
      render(note);
      save(note, { color: note.color }, 0);
    });

    el.querySelector('.sne-note__del').addEventListener('click', async (event) => {
      event.stopPropagation();
      if (!window.confirm(t('confirm'))) {
        return;
      }
      const note = item();
      try {
        await request(`notes/${note.id}`, { method: 'DELETE' });
        el.remove();
        state.notes.delete(note.id);
        state.data.delete(note.id);
      } catch (err) {
        toast(err.message || t('error'));
      }
    });

    el.addEventListener('pointerdown', () => {
      const note = item();
      if (note.z < state.stack) {
        raise(note);
      }
    });

    drag(handle, el, item);
    resize(grip, el, item);
  }

  /**
   * Pointer drag on the top bar. Buttons/inputs are ignored.
   */
  function drag(handle, el, item) {
    handle.addEventListener('pointerdown', (event) => {
      if (event.button !== 0) {
        return;
      }
      if (event.target.closest('button, input, textarea')) {
        return;
      }
      event.preventDefault();
      const note = item();
      const sx = event.clientX;
      const sy = event.clientY;
      const ox = note.x;
      const oy = note.y;
      el.classList.add('is-dragging');
      handle.setPointerCapture(event.pointerId);

      const move = (ev) => {
        const next = clamp(
          ox + (ev.clientX - sx),
          oy + (ev.clientY - sy),
          note.minimized ? Math.min(note.width, 220) : note.width,
          note.minimized ? 42 : note.height
        );
        note.x = next.x;
        note.y = next.y;
        place(el, note);
      };

      const up = () => {
        el.classList.remove('is-dragging');
        handle.releasePointerCapture(event.pointerId);
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', up);
        handle.removeEventListener('pointercancel', up);
        save(note, { x: note.x, y: note.y }, 0);
      };

      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', up);
      handle.addEventListener('pointercancel', up);
    });
  }

  /**
   * Corner resize. In RTL the start edge stays put (x shifts as width grows).
   */
  function resize(handle, el, item) {
    handle.addEventListener('pointerdown', (event) => {
      if (event.button !== 0) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      const note = item();
      const sx = event.clientX;
      const sy = event.clientY;
      const ow = note.width;
      const oh = note.height;
      const ox = note.x;
      const dir = rtl();
      el.classList.add('is-resizing');
      handle.setPointerCapture(event.pointerId);

      const move = (ev) => {
        const dx = dir ? (sx - ev.clientX) : (ev.clientX - sx);
        const next = Math.max(180, Math.min(520, ow + dx));
        note.width = next;
        note.height = Math.max(80, Math.min(720, oh + (ev.clientY - sy)));
        if (dir) {
          note.x = ox - (next - ow);
        }
        place(el, note);
      };

      const up = () => {
        el.classList.remove('is-resizing');
        handle.releasePointerCapture(event.pointerId);
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', up);
        handle.removeEventListener('pointercancel', up);
        const patch = { width: note.width, height: note.height };
        if (dir) {
          patch.x = note.x;
        }
        save(note, patch, 0);
      };

      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', up);
      handle.addEventListener('pointercancel', up);
    });
  }

  /**
   * Look up a note object by numeric id.
   */
  function find(id) {
    return state.data.get(id) || null;
  }

  /**
   * POST a blank page-scoped note and focus its body.
   */
  async function create() {
    if (state.notes.size >= (cfg.max || 40)) {
      toast(t('error'));
      return;
    }

    const offset = (state.notes.size % 8) * 22;
    const pos = spawn(240, 220, offset);

    try {
      const note = await request('notes', {
        method: 'POST',
        body: JSON.stringify({
          title: '',
          content: '',
          color: 'yellow',
          scope: 'page',
          path: path(),
          heading: heading(),
          x: pos.x,
          y: pos.y,
          width: 240,
          height: 220,
          z: state.stack + 1,
          minimized: false,
        }),
      });
      const saved = remember(note);
      state.stack = Math.max(state.stack, saved.z);
      const el = render(saved);
      const body = el.querySelector('.sne-note__body');
      body.focus();
    } catch (err) {
      toast(err.message || t('error'));
    }
  }

  /**
   * Temporarily hide notes in this browser only (localStorage).
   */
  function conceal(hidden) {
    state.hidden = hidden;
    app.classList.toggle('is-hidden', hidden);
    window.localStorage.setItem('stne-hidden', hidden ? '1' : '0');
    const btn = app.querySelector('.sne-fab--hide');
    if (btn) {
      btn.classList.toggle('is-on', hidden);
      btn.innerHTML = hidden ? svg.shut : svg.eye;
      btn.title = hidden ? t('show') : t('hide');
      btn.setAttribute('aria-label', btn.title);
      btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
    }
  }

  /**
   * Floating + / list / hide buttons.
   */
  function dock() {
    const wrap = document.createElement('div');
    wrap.className = 'sne-dock';
    wrap.innerHTML = `
      <button type="button" class="sne-fab" data-action="add">${svg.plus}</button>
      <button type="button" class="sne-fab sne-fab--sm" data-action="tray">${svg.list}</button>
      <button type="button" class="sne-fab sne-fab--sm sne-fab--hide" data-action="hide">${svg.eye}</button>
    `;
    const add = wrap.querySelector('[data-action="add"]');
    const traybtn = wrap.querySelector('[data-action="tray"]');
    const hide = wrap.querySelector('[data-action="hide"]');
    add.title = t('add');
    add.setAttribute('aria-label', t('add'));
    traybtn.title = t('all');
    traybtn.setAttribute('aria-label', t('all'));
    hide.title = t('hide');
    hide.setAttribute('aria-label', t('hide'));
    add.addEventListener('click', create);
    traybtn.addEventListener('click', () => tray(true));
    hide.addEventListener('click', () => conceal(!state.hidden));
    app.appendChild(wrap);
  }

  /**
   * One row in the All notes drawer.
   */
  function row(note) {
    const title = note.title || note.content.slice(0, 42) || t('untitled');
    const page = note.scope === 'global'
      ? t('everywhere')
      : (note.heading || note.path || t('page'));
    const hex = colors[note.color] || '#fff3a3';
    const wrap = document.createElement('button');
    wrap.type = 'button';
    wrap.className = 'sne-item';
    wrap.innerHTML = `
      <span class="sne-item__swatch" style="background:${hex}"></span>
      <span class="sne-item__body">
        <span class="sne-item__title"></span>
        <div class="sne-item__meta"></div>
      </span>
      <span class="sne-item__badge"></span>
    `;
    wrap.querySelector('.sne-item__title').textContent = title;
    wrap.querySelector('.sne-item__meta').textContent = page;
    wrap.querySelector('.sne-item__badge').textContent =
      note.scope === 'global' ? t('everywhere') : t('page');
    wrap.addEventListener('click', () => open(note));
    return wrap;
  }

  /**
   * Open/close the All notes drawer and fetch GET /notes?all=1.
   */
  async function tray(show) {
    state.drawer = show;
    let veil = app.querySelector('.sne-veil');
    let panel = app.querySelector('.sne-tray');

    if (!show) {
      veil?.remove();
      panel?.remove();
      return;
    }

    if (!veil) {
      veil = document.createElement('button');
      veil.type = 'button';
      veil.className = 'sne-veil';
      veil.setAttribute('aria-label', t('close'));
      veil.addEventListener('click', () => tray(false));
      app.appendChild(veil);
    }

    if (!panel) {
      panel = document.createElement('aside');
      panel.className = 'sne-tray';
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', t('all'));
      panel.innerHTML = `
        <div class="sne-tray__head">
          <h2></h2>
          <button type="button" class="sne-icon sne-tray__close"></button>
        </div>
        <div class="sne-tray__list"></div>
      `;
      panel.querySelector('h2').textContent = t('all');
      const close = panel.querySelector('.sne-tray__close');
      close.innerHTML = svg.close;
      close.title = t('close');
      close.setAttribute('aria-label', t('close'));
      close.addEventListener('click', () => tray(false));
      app.appendChild(panel);
    }

    const list = panel.querySelector('.sne-tray__list');
    list.innerHTML = `<div class="sne-tray__empty">…</div>`;

    try {
      const notes = (await request('notes?all=1')).map(remember);
      list.innerHTML = '';
      if (!notes.length) {
        list.innerHTML = `<div class="sne-tray__empty"></div>`;
        list.querySelector('.sne-tray__empty').textContent = t('empty');
        return;
      }
      notes.forEach((note) => list.appendChild(row(note)));
    } catch (err) {
      list.innerHTML = `<div class="sne-tray__empty"></div>`;
      list.querySelector('.sne-tray__empty').textContent = err.message || t('error');
    }
  }

  /**
   * Focus a note, or navigate to its page (#sne-{id}).
   */
  function open(note) {
    tray(false);
    const here = note.scope === 'global' || note.path === path();
    if (!here) {
      window.location.href = note.path + `#sne-${note.id}`;
      return;
    }
    if (state.hidden) {
      conceal(false);
    }
    if (note.minimized) {
      note.minimized = false;
      render(note);
      save(note, { minimized: false }, 0);
    }
    const el = state.notes.get(note.id) || render(note);
    el.classList.remove('is-pulse');
    void el.offsetWidth;
    el.classList.add('is-pulse');
    el.querySelector('.sne-note__body')?.focus();
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      palettes();
      if (state.drawer) {
        tray(false);
      }
    }
    if ((event.altKey || event.metaKey) && event.key.toLowerCase() === 'n' && !event.shiftKey && !event.ctrlKey) {
      const tag = (event.target && event.target.tagName) || '';
      if (tag === 'INPUT' || tag === 'TEXTAREA' || event.target?.isContentEditable) {
        return;
      }
      event.preventDefault();
      create();
    }
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.sne-note')) {
      palettes();
    }
  });

  window.addEventListener('resize', () => {
    state.notes.forEach((el, id) => {
      const note = find(id);
      if (note) {
        place(el, note);
      }
    });
  });

  /**
   * Show the overlay, load notes for this path, honor #sne-{id}.
   */
  async function boot() {
    app.hidden = false;
    app.setAttribute('dir', rtl() ? 'rtl' : 'ltr');
    dock();
    conceal(state.hidden);

    try {
      const notes = await request(`notes?path=${encodeURIComponent(path())}`);
      notes.forEach((note) => render(remember(note)));
    } catch (err) {
      toast(err.message || t('error'));
    }

    const hash = window.location.hash.replace('#', '');
    if (hash.startsWith('sne-')) {
      const id = Number(hash.slice(4));
      const note = find(id);
      if (note) {
        window.setTimeout(() => open(note), 80);
      }
    }
  }

  boot();
})();
