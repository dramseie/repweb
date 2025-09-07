// assets/components/MegaNavbar.jsx
import React, { useEffect, useMemo, useState } from 'react';

function cx(...xs) { return xs.filter(Boolean).join(' '); }
const hasText = v => typeof v === 'string' && v.trim() !== '';

function NavIcon({ name, className }) {
  if (!hasText(name)) return null;
  return <i className={cx('bi', name, className)} aria-hidden="true" />;
}

// Make user avatar initials if no avatar URL
function initialsOf(nameOrEmail) {
  const s = (nameOrEmail || '').trim();
  if (!s) return '?';
  if (s.includes('@')) return s[0].toUpperCase();
  const parts = s.split(/\s+/).filter(Boolean);
  if (parts.length === 1) return parts[0][0].toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/** Normalize one item from API (snake_case or camelCase) to a common shape */
function normalizeItem(i) {
  const parentId = i.parentId ?? i.parent_id ?? null;
  const megaGroup = i.megaGroup ?? i.mega_group ?? null;
  const dividerBefore = i.dividerBefore ?? i.divider_before ?? false;

  return {
    id: i.id,
    label: i.label,
    url: i.url ?? (Array.isArray(i.children) && i.children.length ? '#' : i.route ?? '#'),
    parentId,
    position: i.position ?? 0,
    icon: i.icon,
    external: !!i.external,
    badge: i.badge ?? null,
    description: i.description ?? null,
    megaGroup,
    dividerBefore: !!dividerBefore,
    children: Array.isArray(i.children) ? i.children.map(normalizeItem) : [],
  };
}

function normalizeData(data) {
  const arr = Array.isArray(data) ? data : [];
  const anyChildren = arr.some(x => Array.isArray(x.children) && x.children.length > 0);
  const norm = arr.map(normalizeItem);
  if (anyChildren) return norm;

  const byId = new Map();
  norm.forEach(i => { i.children = []; byId.set(i.id, i); });
  const roots = [];
  for (const it of norm) {
    if (it.parentId == null) roots.push(it);
    else (byId.get(it.parentId)?.children ?? roots).push(it);
  }
  const sortRec = node => { node.children.sort((a,b)=>(a.position??0)-(b.position??0)); node.children.forEach(sortRec); };
  roots.sort((a,b)=>(a.position??0)-(b.position??0)); roots.forEach(sortRec);
  return roots;
}

const currentPath = (typeof window !== 'undefined' ? window.location.pathname : '/') || '/';
const isItemActive = item => hasText(item?.url) && (item.url==='/'? currentPath==='/' : currentPath.startsWith(item.url));
const someDescendantActive = n => n.children?.some(c => isItemActive(c) || someDescendantActive(c));

function DropdownLink({ item, active }) {
  const body = (
    <div className="d-flex align-items-start gap-2">
      <NavIcon name={item.icon} />
      <div>
        <div className="fw-semibold">
          {item.label}{' '}
          {item.badge ? <span className="badge text-bg-primary ms-1">{item.badge}</span> : null}
        </div>
        {hasText(item.description) && <div className="text-muted small">{item.description}</div>}
      </div>
    </div>
  );
  const props = item.external
    ? { href: item.url || '#', target: '_blank', rel: 'noopener noreferrer' }
    : { href: item.url || '#' };
  return <a className={cx('dropdown-item py-2', active && 'active')} {...props}>{body}</a>;
}

function StandardDropdown({ parent, active }) {
  return (
    <li className="nav-item dropdown">
      <a className={cx('nav-link dropdown-toggle', active && 'active')} href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <NavIcon name={parent.icon} className="me-1" />{parent.label}
      </a>
      <ul className="dropdown-menu">
        {parent.children.map(child => (
          <li key={child.id}>
            {child.dividerBefore ? <hr className="dropdown-divider" /> : null}
            <DropdownLink item={child} active={isItemActive(child)} />
          </li>
        ))}
      </ul>
    </li>
  );
}

function MegaDropdown({ parent, active }) {
  const groups = useMemo(() => {
    const m = new Map();
    for (const c of parent.children) {
      const g = hasText(c.megaGroup) ? c.megaGroup.trim() : 'Other';
      if (!m.has(g)) m.set(g, []);
      m.get(g).push(c);
    }
    for (const arr of m.values()) arr.sort((a,b)=>(a.position??0)-(b.position??0));
    return Array.from(m.entries());
  }, [parent.children]);

  return (
    <li className="nav-item dropdown mega-dropdown">
      <a className={cx('nav-link dropdown-toggle', active && 'active')} href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <NavIcon name={parent.icon} className="me-1" />{parent.label}
      </a>
      <div className="dropdown-menu mega-dropdown p-3">
        <div className="container-fluid">
          <div className="row g-4">
            {groups.map(([groupName, items]) => (
              <div className="col-12 col-md-6 col-lg-4" key={groupName}>
                <div className="mb-2 fw-semibold text-uppercase small text-muted">{groupName}</div>
                <ul className="list-unstyled m-0">
                  {items.map(item => (
                    <li key={item.id} className="mb-1">
                      {item.dividerBefore ? <hr className="dropdown-divider my-2" /> : null}
                      <DropdownLink item={item} active={isItemActive(item)} />
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </div>
    </li>
  );
}

function TopLink({ item, active }) {
  const props = item.external
    ? { href: item.url || '#', target: '_blank', rel: 'noopener noreferrer' }
    : { href: item.url || '#' };
  return (
    <li className="nav-item">
      <a className={cx('nav-link', active && 'active')} {...props}>
        <NavIcon name={item.icon} className="me-1" />{item.label}
      </a>
    </li>
  );
}

export default function MegaNavbar({
  logoSrc = '/images/logo.png',
  brandHref = '/',
  currentUser = null,            // { name, email, avatarUrl }
  logoutPath = '/logout',
  csrfToken = '',               // pass if your logout requires it
}) {
  const [roots, setRoots] = useState([]);
  const [state, setState] = useState('loading'); // loading | ready | error
  const [err, setErr] = useState('');

  useEffect(() => {
    const ac = new AbortController();
    (async () => {
      try {
        const res = await fetch('/api/menu', { signal: ac.signal, credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        setRoots(normalizeData(json));
        setState('ready');
      } catch (e) {
        if (!ac.signal.aborted) { setErr(e.message || 'Failed to load menu'); setState('error'); }
      }
    })();
    return () => ac.abort();
  }, []);

  // Init Bootstrap dropdowns after render
  useEffect(() => {
    if (!roots.length || !window.bootstrap) return;
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
      try { window.bootstrap.Dropdown.getOrCreateInstance(el); } catch {}
    });
  }, [roots]);

  const topRenderable = useMemo(
    () => roots.filter(t => hasText(t.url) || (t.children?.length > 0)),
    [roots]
  );

  // Helpers
  const dispatchWidgets = (action) =>
    window.dispatchEvent(new CustomEvent('widgets.action', { detail: { action } }));

  const hideClosestDropdown = (el) => {
    const root = el.closest('.dropdown');
    const toggle = root?.querySelector('[data-bs-toggle="dropdown"]');
    if (toggle && window.bootstrap) {
      try { window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide(); } catch {}
    }
  };

  const goToWidgetsHome = (e) => {
    e.preventDefault();
    hideClosestDropdown(e.currentTarget);
    window.location.assign('/'); // <- your widgets live on Home
  };

  const clickAndDispatch = (e, action) => {
    e.preventDefault();
    hideClosestDropdown(e.currentTarget);
    dispatchWidgets(action);
  };

  return (
    <nav className="navbar navbar-expand-lg navbar-light meganavbar">
      <div className="container-fluid">
        <a className="navbar-brand d-flex align-items-center" href={brandHref}>
          <img src={logoSrc} alt="Logo" />
        </a>

        <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#repwebNavbar" aria-controls="repwebNavbar" aria-expanded="false" aria-label="Toggle navigation">
          <span className="navbar-toggler-icon" />
        </button>

        <div className="collapse navbar-collapse" id="repwebNavbar">
          <ul className="navbar-nav ms-3 mb-2 mb-lg-0">
            {state === 'loading' && <li className="nav-item"><span className="nav-link disabled">Loading…</span></li>}
            {state === 'error' && <li className="nav-item"><span className="nav-link text-danger">Menu error: {err}</span></li>}
            {state === 'ready' && topRenderable.map(item => {
              const hasChildren = item.children && item.children.length > 0;
              const active = isItemActive(item) || someDescendantActive(item);
              if (!hasChildren) return <TopLink key={item.id} item={item} active={active} />;
              const isMega = item.children.some(c => hasText(c.megaGroup));
              return isMega
                ? <MegaDropdown key={item.id} parent={item} active={active} />
                : <StandardDropdown key={item.id} parent={item} active={active} />;
            })}
          </ul>

          {/* Right side: user dropdown */}
          <div className="ms-auto">
            {currentUser ? (
              <div className="dropdown">
                <button
                  className="btn btn-outline-dark btn-sm d-flex align-items-center gap-2"
                  data-bs-toggle="dropdown"
                  aria-expanded="false"
                >
                  {currentUser.avatarUrl ? (
                    <img src={currentUser.avatarUrl} alt="" width="28" height="28" className="rounded-circle" />
                  ) : (
                    <span
                      className="rounded-circle d-inline-flex align-items-center justify-content-center fw-semibold"
                      style={{ width: 28, height: 28, background: '#e9ecef' }}
                    >
                      {initialsOf(currentUser.name || currentUser.email)}
                    </span>
                  )}
                  <span className="d-none d-sm-inline">{currentUser.name || currentUser.email}</span>
                </button>
                <ul className="dropdown-menu dropdown-menu-end">
                  <li className="px-3 py-2">
                    <div className="fw-semibold">{currentUser.name || 'User'}</div>
                    {currentUser.email && <div className="text-muted small">{currentUser.email}</div>}
                  </li>
                  <li><hr className="dropdown-divider" /></li>

                  {/* Widgets section */}
                  <li><div className="dropdown-header">Widgets</div></li>
                  <li>
                    <a className="dropdown-item" href="/" onClick={goToWidgetsHome}>
                      <i className="bi bi-grid-3x3-gap me-2" />
                      My Widgets
                    </a>
                  </li>
                  <li>
                    <button
                      className="dropdown-item"
                      type="button"
                      onClick={(e) => clickAndDispatch(e, 'widgets:edit')}
                    >
                      <i className="bi bi-pencil-square me-2" />
                      Edit layout
                    </button>
                  </li>
                  <li>
                    <button
                      className="dropdown-item"
                      type="button"
                      onClick={(e) => clickAndDispatch(e, 'widgets:add')}
                    >
                      <i className="bi bi-plus-square me-2" />
                      Add widget
                    </button>
                  </li>
                  <li>
                    <button
                      className="dropdown-item"
                      type="button"
                      onClick={(e) => clickAndDispatch(e, 'widgets:save')}
                    >
                      <i className="bi bi-save me-2" />
                      Save
                    </button>
                  </li>
                  <li>
                    <button
                      className="dropdown-item"
                      type="button"
                      onClick={(e) => clickAndDispatch(e, 'widgets:reset')}
                    >
                      <i className="bi bi-arrow-counterclockwise me-2" />
                      Reset
                    </button>
                  </li>

                  <li><hr className="dropdown-divider" /></li>
                  <li>
                    <form method="post" action={logoutPath} className="px-0 m-0">
                      {csrfToken ? <input type="hidden" name="_csrf_token" value={csrfToken} /> : null}
                      <button type="submit" className="dropdown-item">
                        <i className="bi bi-box-arrow-right me-2" />
                        Logout
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
            ) : (
              // Fallback: if we don't have user info yet
              <form method="post" action="/logout">
                <button type="submit" className="btn btn-outline-dark btn-sm">
                  <i className="bi bi-box-arrow-right me-1" /> Logout
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
}
