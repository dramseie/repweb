// assets/react/lib/widgetZoom.js

// Containers where your widgets live
const DASHBOARD_ROOTS = [
  '#react-widgets-dashboard',
  '#widgets-home-root',
  '.widgets-grid',
  '.dashboard-widgets',
  'main .dashboard',
];

// Prefer explicit widget markers if present
const EXPLICIT_WIDGET_SELECTORS = [
  '[data-widget-id]',
  '[data-widget]',
  '.widget',
  '.widget-card',
  '.dash-widget',
  '.card.widget',
  '.card.dashlet',
];

// Helper: is this a top-level card (not nested inside another .card)?
function isTopLevelCard(cardEl) {
  if (!cardEl.classList.contains('card')) return false;
  const ancestorCard = cardEl.parentElement?.closest('.card');
  return !ancestorCard;
}

function inDashboardRoot(el) {
  return Boolean(el.closest(DASHBOARD_ROOTS.join(',')));
}

function isExplicitWidget(el) {
  return EXPLICIT_WIDGET_SELECTORS.some((sel) => el.matches(sel));
}

function findHeader(el) {
  return (
    el.querySelector('.card-header, .panel-heading, [data-widget-title], header') || null
  );
}

function addButton(el) {
  if (el.__zoomified) return;
  el.__zoomified = true;

  el.classList.add('widget-zoomable');

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'widget-zoom-btn';
  btn.title = 'Zoom';
  btn.setAttribute('aria-label', 'Zoom');
  btn.setAttribute('data-zoom-state', 'in');
  btn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';

  const header = findHeader(el);
  if (header) header.classList.add('widget-zoom-header');
  (header || el).appendChild(btn);

  const toggle = () => {
    const goingIn = !el.classList.contains('widget-zoomed');

    // backdrop
    let backdrop = document.querySelector('.widget-zoom-backdrop');
    if (goingIn && !backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'widget-zoom-backdrop';
      document.body.appendChild(backdrop);
      backdrop.addEventListener('click', toggle);
    }

    if (goingIn) {
      // PORTAL: move card to body and remember original spot
      if (!el.__zoomPortal) {
        const placeholder = document.createComment('widget-zoom-placeholder');
        const parent = el.parentNode;
        const next = el.nextSibling;
        parent.insertBefore(placeholder, el);
        document.body.appendChild(el);
        el.__zoomPortal = { placeholder, parent, next };
      }

      el.classList.add('widget-zoomed');
      btn.setAttribute('data-zoom-state', 'out');
      btn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
      btn.title = 'Exit zoom';
      document.body.style.overflow = 'hidden';
      btn.focus();
    } else {
      // restore to original place
      if (el.__zoomPortal) {
        const { placeholder, parent, next } = el.__zoomPortal;
        if (next && next.parentNode === parent) parent.insertBefore(el, next);
        else parent.insertBefore(el, placeholder);
        placeholder.remove();
        el.__zoomPortal = null;
      }

      el.classList.remove('widget-zoomed');
      btn.setAttribute('data-zoom-state', 'in');
      btn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
      btn.title = 'Zoom';
      document.querySelector('.widget-zoom-backdrop')?.remove();
      document.body.style.overflow = '';
    }
  };

  // 🔧 this line was missing – without it, clicks do nothing
  btn.addEventListener('click', toggle);

  // Close on Esc
  const onKey = (e) => {
    if (e.key === 'Escape' && el.classList.contains('widget-zoomed')) toggle();
  };
  document.addEventListener('keydown', onKey);

  // Cleanup if removed from DOM
  const ro = new ResizeObserver(() => {
    if (!document.body.contains(el)) {
      document.removeEventListener('keydown', onKey);
      ro.disconnect();
    }
  });
  ro.observe(document.body);
}

export function initWidgetZoom(root = document) {
  const target = root === document ? document.body : root;

  // 1) Explicit widget wrappers (top-level only)
  root.querySelectorAll(EXPLICIT_WIDGET_SELECTORS.join(',')).forEach((node) => {
    if (!inDashboardRoot(node)) return;
    const card = node.classList.contains('card') ? node : node.querySelector(':scope > .card, .card');
    if (card && isTopLevelCard(card)) addButton(card);
  });

  // 2) Plain cards directly under a dashboard root (top-level only)
  DASHBOARD_ROOTS.forEach((scopeSel) => {
    root.querySelectorAll(`${scopeSel} .card`).forEach((card) => {
      if (isTopLevelCard(card)) addButton(card);
    });
  });

  // Observe future changes (add-only)
  const mo = new MutationObserver((muts) => {
    muts.forEach((m) => {
      m.addedNodes.forEach((n) => {
        if (!(n instanceof HTMLElement)) return;

        if (inDashboardRoot(n) && isExplicitWidget(n)) {
          const card = n.classList.contains('card') ? n : n.querySelector(':scope > .card, .card');
          if (card && isTopLevelCard(card)) addButton(card);
        }

        if (inDashboardRoot(n) && n.classList.contains('card') && isTopLevelCard(n)) {
          addButton(n);
        }

        n.querySelectorAll?.(EXPLICIT_WIDGET_SELECTORS.join(',')).forEach((w) => {
          if (!inDashboardRoot(w)) return;
          const c = w.classList.contains('card') ? w : w.querySelector(':scope > .card, .card');
          if (c && isTopLevelCard(c)) addButton(c);
        });

        DASHBOARD_ROOTS.forEach((scopeSel) => {
          n.querySelectorAll?.(`${scopeSel} .card`).forEach((c) => {
            if (isTopLevelCard(c)) addButton(c);
          });
        });
      });
    });
  });
  mo.observe(target, { childList: true, subtree: true });

  // Debug helpers (optional)
  window.DEBUG_WIDGET_ZOOM && console.debug('[widgetZoom] init complete');
  window.__forceWidgetZoom = () => initWidgetZoom(document);

  return () => mo.disconnect();
}
