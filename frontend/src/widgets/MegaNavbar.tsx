
import React, { useEffect, useMemo, useState } from 'react';

export type MenuNode = {
  id: number;
  label: string;
  url: string;
  icon?: string | null;
  external?: boolean;
  megaGroup?: string | null;
  children?: MenuNode[];
};

const MegaNavbar: React.FC = () => {
  const [menu, setMenu] = useState<MenuNode[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const ctrl = new AbortController();
    fetch('/api/menu', { signal: ctrl.signal })
      .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then((json) => setMenu(json))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
    return () => ctrl.abort();
  }, []);

  const content = useMemo(() => {
    if (loading) return <span className="navbar-text text-muted">Loading…</span>;
    if (error) return <span className="navbar-text text-danger">{error}</span>;

    return (
      <ul className="navbar-nav ms-auto">
        {menu.map(item => {
          const hasChildren = !!(item.children && item.children.length > 0);
          if (!hasChildren) {
            return (
              <li key={item.id} className="nav-item">
                <a className="nav-link" href={item.url}>{item.label}</a>
              </li>
            );
          }

          // Group children by megaGroup
          const groups: Record<string, MenuNode[]> = {};
          item.children!.forEach(c => {
            const key = c.megaGroup || c.label;
            groups[key] = groups[key] || [];
            groups[key].push(c);
          });

          return (
            <li key={item.id} className="nav-item dropdown position-static">
              <a className="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                {item.label}
              </a>
              <div className="dropdown-menu w-100 mt-0 p-3 border-0 shadow mega-dropdown">
                <div className="container">
                  <div className="row g-4">
                    {Object.entries(groups).map(([groupLabel, links]) => (
                      <div key={groupLabel} className="col-12 col-md-4">
                        <h6 className="text-uppercase small fw-bold text-muted mb-2">{groupLabel}</h6>
                        <ul className="list-unstyled mb-0">
                          {links.map(link => (
                            <li key={link.id}>
                              <a className="dropdown-item d-flex align-items-center py-2" href={link.url} {...(link.external ? { target: '_blank', rel: 'noopener' } : {})}>
                                {link.icon && <i className={`me-2 ${link.icon}`}></i>}
                                <span>{link.label}</span>
                              </a>
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
        })}
      </ul>
    );
  }, [menu, loading, error]);

  return (
    <nav className="navbar navbar-expand-lg navbar-dark bg-dark meganavbar">
      <div className="container-fluid">
        <a className="navbar-brand d-flex align-items-center" href="/">
          <span className="logo-square me-2" />
          repweb
        </a>
        <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
          <span className="navbar-toggler-icon"></span>
        </button>
        <div className="collapse navbar-collapse" id="mainNav">
          {content}
        </div>
      </div>
    </nav>
  );
};

export default MegaNavbar;
