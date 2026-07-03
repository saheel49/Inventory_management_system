/* =======================================================
   InvenPro – main.js  v3.0
   ======================================================= */
'use strict';

/* ---- PAGE LOADER ---- */
window.addEventListener('load', () => {
  const l = document.getElementById('pageLoader');
  if (l) { l.classList.add('hidden'); setTimeout(() => l.remove(), 500); }
});

/* ---- TOAST ---- */
function showToast(type, title, msg, ms = 4200) {
  const c = document.getElementById('toastContainer');
  if (!c) return;
  const ic = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
  const cl = { success:'#10B981', error:'#EF4444', warning:'#F59E0B', info:'#06B6D4' };
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<i class="toast-icon fa-solid ${ic[type]||ic.info}" style="color:${cl[type]||cl.info}"></i>
    <div style="flex:1"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>
    <button class="toast-close"><i class="fa-solid fa-xmark"></i></button>
    <div class="toast-progress"></div>`;
  c.appendChild(t);
  const timer = setTimeout(() => removeTst(t), ms);
  t.querySelector('.toast-close').addEventListener('click', () => { clearTimeout(timer); removeTst(t); });
}
function removeTst(t) {
  if (!t) return;
  t.classList.add('removing');
  setTimeout(() => t.remove(), 280);
}
window.showToast = showToast;

/* ---- MODAL ---- */
function openModal(id)  { document.getElementById(id)?.classList.add('active'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('active'); }
window.openModal  = openModal;
window.closeModal = closeModal;
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active')); });

/* ---- DELETE CONFIRM ---- */
function confirmDelete(url, desc) {
  const modal = document.getElementById('deleteModal');
  if (!modal) return true;
  document.getElementById('deleteModalDesc').textContent = desc || 'Are you sure? This cannot be undone.';
  document.getElementById('deleteModalConfirm').href = url;
  openModal('deleteModal');
  return false;
}
window.confirmDelete = confirmDelete;

/* ---- SIDEBAR ---- */
document.addEventListener('DOMContentLoaded', () => {
  const sb  = document.getElementById('sidebar');
  const tog = document.getElementById('sidebarToggle');
  const cls = document.getElementById('sidebarClose');

  tog?.addEventListener('click', e => { e.stopPropagation(); sb?.classList.toggle('open'); });
  cls?.addEventListener('click', () => sb?.classList.remove('open'));
  document.addEventListener('click', e => {
    if (sb?.classList.contains('open') && !sb.contains(e.target) && e.target !== tog)
      sb.classList.remove('open');
  });

  /* submenu accordion */
  document.querySelectorAll('.has-submenu > .nav-item').forEach(el => {
    el.addEventListener('click', e => {
      if (el.getAttribute('href') === '#') e.preventDefault();
      el.closest('.has-submenu')?.classList.toggle('open');
    });
  });

  /* highlight active nav link */
  const path = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach(a => {
    const href = a.getAttribute('href');
    if (href && href !== '#' && path.includes(href.split('?')[0])) {
      a.classList.add('active');
      const ps = a.closest('.submenu');
      if (ps) ps.closest('.has-submenu')?.classList.add('open');
    }
  });

  /* ensure sidebar links navigate even if something blocks default navigation */
  document.querySelectorAll('#sidebar .nav-item').forEach(a => {
    a.addEventListener('click', e => {
      const href = a.getAttribute('href');
      if (!href || href === '#') return;
      // if default navigation prevented elsewhere, force it
      setTimeout(() => { if (window.location.href !== a.href) window.location.assign(a.href); }, 20);
    });
  });
});

/* ---- USER DROPDOWN ---- */
document.addEventListener('DOMContentLoaded', () => {
  const btn  = document.getElementById('userMenuBtn');
  const drop = document.getElementById('userDropdown');
  btn?.addEventListener('click', e => { e.stopPropagation(); drop?.classList.toggle('active'); });
  document.addEventListener('click', () => drop?.classList.remove('active'));
});

/* ---- THEME TOGGLE ---- */
document.addEventListener('DOMContentLoaded', () => {
  const btn  = document.getElementById('themeToggle');
  const icon = document.getElementById('themeIcon');

  function applyTheme(theme) {
    const dark = theme === 'dark';
    document.documentElement.classList.toggle('dark-mode', dark);
    document.body.classList.toggle('dark-mode', dark);
    if (icon) icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    if (btn)  btn.title = dark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
  }

  const saved = localStorage.getItem('inventory_theme');
  const init  = saved || (document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light');
  applyTheme(init);

  btn?.addEventListener('click', () => {
    const next = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
    localStorage.setItem('inventory_theme', next);
    applyTheme(next);
    fetch(window.BASE_URL + '/tools/toggle_theme.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ theme: next })
    }).catch(() => {});
  });
});

/* ---- FULLSCREEN ---- */
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('fullscreenBtn');
  btn?.addEventListener('click', () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen();
      btn.innerHTML = '<i class="fa-solid fa-compress"></i>';
    } else {
      document.exitFullscreen();
      btn.innerHTML = '<i class="fa-solid fa-expand"></i>';
    }
  });
});

/* ---- GLOBAL SEARCH (fixes dropdown overlapping page) ---- */
document.addEventListener('DOMContentLoaded', () => {
  const inp = document.getElementById('quickSearchInput');
  const res = document.getElementById('searchResults');
  if (!inp || !res) return;

  let timer;
  function clear() { res.classList.remove('active'); res.innerHTML = ''; }

  inp.addEventListener('input', () => {
    clearTimeout(timer);
    const q = inp.value.trim();
    if (q.length < 2) { clear(); return; }
    timer = setTimeout(() => {
      fetch(`${window.BASE_URL}/api/search.php?q=${encodeURIComponent(q)}&type=all`)
        .then(r => r.json())
        .then(data => {
          if (!data.length) {
            res.innerHTML = '<div class="sr-empty"><i class="fa-solid fa-magnifying-glass" style="margin-right:6px;opacity:.4;"></i>No results found</div>';
          } else {
            res.innerHTML = data.slice(0, 12).map(item => `
              <div class="sr-item" onclick="window.location.href='${item.url}'" style="cursor:pointer;">
                <div class="sr-icon">${item.icon}</div>
                <div style="min-width:0;">
                  <div class="sr-name">${item.name}</div>
                  <div class="sr-type">${item.type}${item.sub ? ' &middot; ' + item.sub : ''}</div>
                </div>
              </div>`).join('');
          }
          res.classList.add('active');
        }).catch(clear);
    }, 220);
  });

  inp.addEventListener('keydown', e => { if (e.key === 'Escape') clear(); });
  document.addEventListener('click', e => {
    if (!inp.contains(e.target) && !res.contains(e.target)) clear();
  });
});

/* ---- PAGE AUTOCOMPLETE (customers/suppliers/products search bars) ---- */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.page-autocomplete').forEach(wrapper => {
    const inp  = wrapper.querySelector('input[type="text"]');
    const drop = wrapper.querySelector('.autocomplete-list');
    const type = wrapper.dataset.searchType || 'product';
    if (!inp || !drop) return;

    let timer;
    function clear() { drop.innerHTML = ''; drop.classList.remove('active'); }

    inp.addEventListener('input', () => {
      clearTimeout(timer);
      const q = inp.value.trim();
      if (q.length < 2) { clear(); return; }
      timer = setTimeout(() => {
        fetch(`${window.BASE_URL}/api/search.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`)
          .then(r => r.json())
          .then(data => {
            if (!data.length) {
              drop.innerHTML = '<div class="ac-item no-result">No results found</div>';
            } else {
              drop.innerHTML = data.slice(0, 10).map(item => `
                <div class="ac-item" data-url="${item.url}">
                  <div class="ac-title">${item.name}<span class="ac-badge">${item.type}</span></div>
                  <div class="ac-meta">${item.sub ? item.sub : item.type + ' – click to open'}</div>
                </div>`).join('');
            }
            drop.classList.add('active');
          }).catch(clear);
      }, 200);
    });

    drop.addEventListener('click', e => {
      const item = e.target.closest('.ac-item');
      if (item?.dataset.url) window.location.href = item.dataset.url;
    });
    document.addEventListener('click', e => { if (!wrapper.contains(e.target)) clear(); });
  });
});

/* ---- ANIMATED COUNTERS ---- */
function animateCounter(el, target) {
  const final = Number.isInteger(target) ? target.toLocaleString() : target.toFixed(1);
  el.textContent = final; // show real value immediately
  if (target === 0) return;
  let start = null;
  const dur = 1200;
  const ease = t => 1 - Math.pow(1 - t, 3);
  function step(ts) {
    if (!start) start = ts;
    const p = Math.min((ts - start) / dur, 1);
    el.textContent = Number.isInteger(target)
      ? Math.round(ease(p) * target).toLocaleString()
      : (ease(p) * target).toFixed(1);
    if (p < 1) requestAnimationFrame(step);
    else el.textContent = final;
  }
  requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-count]').forEach(el => {
    const v = parseFloat(el.dataset.count);
    if (!isNaN(v)) {
      el.textContent = Number.isInteger(v) ? v.toLocaleString() : v.toFixed(1);
      setTimeout(() => animateCounter(el, v), 120);
    }
  });
});

/* ---- ALERT AUTO-DISMISS & CLOSE ---- */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert[data-autodismiss]').forEach(a => {
    const ms = parseInt(a.dataset.autodismiss) || 5000;
    setTimeout(() => { a.style.transition='opacity .4s'; a.style.opacity='0'; setTimeout(()=>a.remove(),400); }, ms);
  });
  document.querySelectorAll('.alert-close').forEach(b => b.addEventListener('click', () => b.closest('.alert')?.remove()));
});

/* ---- BACK TO TOP ---- */
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('backToTop');
  if (!btn) return;
  window.addEventListener('scroll', () => btn.classList.toggle('visible', window.scrollY > 320));
  btn.addEventListener('click', () => window.scrollTo({ top:0, behavior:'smooth' }));
});

/* ---- LEDGER KEYBOARD NAV ---- */
document.addEventListener('DOMContentLoaded', () => {
  const tbl = document.querySelector('.ledger-datatable');
  if (!tbl) return;
  const rows = Array.from(tbl.querySelectorAll('tbody tr'));
  let sel = -1;
  document.addEventListener('keydown', e => {
    if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel+1, rows.length-1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel-1, -1); }
    else return;
    rows.forEach((r,i) => r.classList.toggle('keyboard-selected', i===sel));
    if (sel>=0) rows[sel].scrollIntoView({ block:'nearest' });
  });
});

/* ---- STOCK IN/OUT MUTEX ---- */
document.addEventListener('DOMContentLoaded', () => {
  const si = document.getElementById('stock_in');
  const so = document.getElementById('stock_out');
  if (si && so) {
    si.addEventListener('input', () => { if (parseFloat(si.value)>0) so.value=0; });
    so.addEventListener('input', () => { if (parseFloat(so.value)>0) si.value=0; });
  }
});

/* ---- RIPPLE ---- */
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn');
  if (!btn || btn.disabled) return;
  const r   = btn.getBoundingClientRect();
  const sz  = Math.max(r.width, r.height);
  const sp  = document.createElement('span');
  sp.style.cssText = `position:absolute;width:${sz}px;height:${sz}px;border-radius:50%;`
    + `background:rgba(255,255,255,0.28);transform:scale(0);`
    + `animation:ripple .55s linear;top:${e.clientY-r.top-sz/2}px;left:${e.clientX-r.left-sz/2}px;pointer-events:none;`;
  if (!document.getElementById('_ripple_style')) {
    const s = document.createElement('style');
    s.id = '_ripple_style';
    s.textContent = '@keyframes ripple{to{transform:scale(2.8);opacity:0}}';
    document.head.appendChild(s);
  }
  btn.style.position = 'relative';
  btn.style.overflow = 'hidden';
  btn.appendChild(sp);
  setTimeout(() => sp.remove(), 600);
});
