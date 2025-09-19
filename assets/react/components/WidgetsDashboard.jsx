// assets/react/components/WidgetsDashboard.jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Responsive, WidthProvider } from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';

// Modal picker (kept for backward compatibility; tabs can trigger add events)
import AddWidgetModal from './widgets/AddWidgetModal';

import LeafletEavMap from './widgets/LeafletEavMap';

// Real widgets
import PlotlyWidget from './widgets/PlotlyWidget';
import DataTableWidget from './widgets/DataTableWidget';
import PivotWidget from './widgets/PivotWidget';
import GrafanaWidget from './widgets/GrafanaWidget';
import MarkdownWidget from './widgets/MarkdownWidget';
import KpiWidget from './widgets/KpiWidget';
import NiFiWidget from './widgets/NiFiWidget'; // 👈 NEW
import CameraLiveWidget from './widgets/CameraLiveWidget'; // 👈 NEW
import CheckmkWidget from './widgets/CheckmkWidget'; // 👈 NEW
import RocketChatLoaderWidget from './widgets/RocketChatLoaderWidget';
import ManageTabsModal from './widgets/ManageTabsModal';
import CaraLuckyWidget from './widgets/CaraLuckyWidget';

const ResponsiveGridLayout = WidthProvider(Responsive);

const BREAKPOINTS = { lg: 1200, md: 996, sm: 768, xs: 480, xxs: 0 };
const COLS        = { lg: 12,   md: 10,  sm: 8,   xs: 6,   xxs: 4 };

// Optional global config injected by Twig:
// <script>window.REPWEB = { rocketchat: { url:'wss://...', userId:'...', token:'...', roomId:'...' } };</script>
const RC_CFG = (window.REPWEB && window.REPWEB.rocketchat) || {};

function Toolbar({ editing, onToggleEdit, onAdd, onSave, onReset }) {
  return (
    <div className="d-flex gap-2 mb-2">
      <button className={`btn ${editing ? 'btn-warning' : 'btn-outline-secondary'}`} onClick={onToggleEdit}>
        {editing ? 'Lock layout' : 'Edit layout'}
      </button>
      <button className="btn btn-primary" onClick={onAdd}>Add widget</button>
      <button className="btn btn-success" onClick={onSave}>Save</button>
      <button className="btn btn-outline-danger" onClick={onReset}>Reset</button>
    </div>
  );
}

function WidgetChrome({ title, editing, onRemove, children }) {
  return (
    <div className="card h-100 shadow-sm">
      <div className="card-header py-2 d-flex align-items-center justify-content-between">
        <strong className="small text-uppercase">{title}</strong>
        {editing && (
          <button
            type="button"
            className="btn btn-sm btn-outline-danger widget-close-btn"
            title="Remove"
            onMouseDown={(e) => e.stopPropagation()}
            onClick={(e) => { e.stopPropagation(); onRemove(); }}
          >
            ✕
          </button>
        )}
      </div>
      <div className="card-body p-2" style={{ overflow: 'auto' }}>{children}</div>
    </div>
  );
}

export default function WidgetsDashboard({ apiBase = '/api/widgets' }) {
  const [defs, setDefs] = useState([]);
  const [items, setItems] = useState([]);
  const [layouts, setLayouts] = useState({ lg: [], md: [], sm: [], xs: [], xxs: [] });
  const [editing, setEditing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);
  const [showAdd, setShowAdd] = useState(false);

  // 🔧 NEW: Manage Tabs modal visibility
  const [showManageTabs, setShowManageTabs] = useState(false);

  // Toggle to show/hide the local toolbar
  const SHOW_LOCAL_TOOLBAR = false;

  // Refs to always have latest state inside global event handlers
  const itemsRef = useRef(items);
  const layoutsRef = useRef(layouts);
  useEffect(() => { itemsRef.current = items; }, [items]);
  useEffect(() => { layoutsRef.current = layouts; }, [layouts]);

  useEffect(() => {
    (async () => {
      try {
        const now = Date.now(); // cache-buster
        const [defsRes, layoutRes] = await Promise.all([
          fetch(`${apiBase}/defs?_=${now}`,   { cache: 'no-store' }),
          fetch(`${apiBase}/layout?_=${now}`, { cache: 'no-store' }),
        ]);
        if (!defsRes.ok || !layoutRes.ok) throw new Error('Load failed');
        const defsJson = await defsRes.json();
        const layoutJson = await layoutRes.json();

        // 🔧 Ensure Camera Live appears, even if backend not updated yet
        const hasCamera = Array.isArray(defsJson) && defsJson.some(d => d.type === 'camera-live');
        const cameraDef = {
          type: 'camera-live',
          title: 'Camera Live',
          subtitle: 'HLS stream player',
          w: 6, h: 6, minW: 3, minH: 3,
          defaults: {
            src: '/hlsdisk/sms18/index.m3u8',
            poster: '',
            muted: true,
            autoPlay: true,
            controls: true,
            objectFit: 'cover',
          },
          tags: ['video','camera','live'],
        };

        // 🔧 Ensure CheckMK Health appears as a selectable/known widget type
        const hasCheckmk = Array.isArray(defsJson) && defsJson.some(d => d.type === 'checkmk');
        const checkmkDef = {
          type: 'checkmk',
          title: 'CheckMK Health',
          subtitle: 'Hosts & Services status',
          w: 6, h: 6, minW: 3, minH: 3,
          defaults: {
            apiBase: '/api/checkmk',
            refreshMs: 30000
          },
          tags: ['monitoring','checkmk','ops'],
        };

        // ✅ Ensure Rocket.Chat realtime appears as a selectable/known widget type
        const hasRC = Array.isArray(defsJson) && defsJson.some(d => d.type === 'rocketchat');
        const rocketchatDef = {
          type: 'rocketchat',
          title: 'Rocket.Chat (Live)',
          subtitle: 'Realtime room stream over WebSocket',
          w: 6, h: 6, minW: 3, minH: 3,
          defaults: {
            configUrl: '/api/rocketchat/config',
            maxMessages: 20
          },
          tags: ['chat','realtime','rocketchat'],
        };

        // 🐾 ✅ Ensure Caraméow Lucky widget appears
        const hasCaraLucky = Array.isArray(defsJson) && defsJson.some(d => d.type === 'caralucky');
        const caraLuckyDef = {
          type: 'caralucky',
          title: 'Caraméow Lucky Numbers',
          subtitle: 'Seedable multi-lottery picker',
          w: 6, h: 5, minW: 3, minH: 3,
          defaults: {
            game: 'euromillions'
          },
          tags: ['fun','luck','carameow'],
        };

        let mergedDefs = Array.isArray(defsJson) ? [...defsJson] : [];
        if (!hasCamera)    mergedDefs.push(cameraDef);
        if (!hasCheckmk)   mergedDefs.push(checkmkDef);
        if (!hasRC)        mergedDefs.push(rocketchatDef);
        if (!hasCaraLucky) mergedDefs.push(caraLuckyDef);

        setDefs(mergedDefs);
        setItems(layoutJson.items || []);
        setLayouts({
          lg: layoutJson.layouts?.lg || [],
          md: layoutJson.layouts?.md || [],
          sm: layoutJson.layouts?.sm || [],
          xs: layoutJson.layouts?.xs || [],
          xxs: layoutJson.layouts?.xxs || [],
        });

      } catch (e) {
        console.error(e);
        setErr('Failed to load dashboard');
      } finally {
        setLoading(false);
      }
    })();
  }, [apiBase]);

  function onLayoutsChange(_layout, all) {
    setLayouts(all);
  }

  function removeWidget(id) {
    setItems(prev => prev.filter(w => w.id !== id));
    setLayouts(prev =>
      Object.fromEntries(
        Object.entries(prev).map(([bp, arr]) => [bp, arr.filter(l => l.i !== id)])
      )
    );
  }

  function addWidget(def) {
    const id = `w_${Math.random().toString(36).slice(2, 9)}`;
    const item = { id, type: def.type, title: def.title, props: def.defaults || {} };
    const lg = [
      ...(layoutsRef.current.lg || []),
      { i: id, x: 0, y: Infinity, w: def.w || 4, h: def.h || 5, minW: def.minW || 2, minH: def.minH || 2 }
    ];
    setItems(prev => [...prev, item]);
    setLayouts(prev => ({ ...prev, lg }));
    if (!editing) setEditing(true);
  }

  // Save using refs to ensure freshest state when called from global handler
  async function saveCurrent() {
    const payload = { version: 1, items: itemsRef.current, layouts: layoutsRef.current };
    const res = await fetch(`${apiBase}/layout`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw new Error('Save failed');
  }

  async function reset() {
    const now = Date.now();
    const res = await fetch(`${apiBase}/layout?_=${now}`, { cache: 'no-store' });
    if (!res.ok) return;
    const json = await res.json();
    setItems(json.items || []);
    setLayouts({
      lg: json.layouts?.lg || [],
      md: json.layouts?.md || [],
      sm: json.layouts?.sm || [],
      xs: json.layouts?.xs || [],
      xxs: json.layouts?.xxs || [],
    });
  }

  // 🔗 Listen once for navbar dropdown actions (tabs or menu dispatches)
  useEffect(() => {
    const handler = (ev) => {
      const action = ev.detail?.action;
      switch (action) {
        case 'widgets:edit':
          setEditing(v => !v);
          break;
        case 'widgets:add':
          setShowAdd(true);
          break;
        case 'widgets:save':
          saveCurrent().catch(e => alert(e.message));
          break;
        case 'widgets:reset':
          reset();
          break;
        // Optional: allow adding known widget types directly via event
        // e.g., detail = { action:'widgets:add-type', type:'checkmk' }
        case 'widgets:add-type': {
          const t = ev.detail?.type;
          const def = (defs || []).find(d => d.type === t);
          if (def) addWidget(def);
          break;
        }
        // 🔧 NEW: open the Manage Tabs modal from global dispatch
        case 'tabs:manage':
          setShowManageTabs(true);
          break;
        default:
          break;
      }
    };
    window.addEventListener('widgets.action', handler);

    // 🔧 NEW: also listen to a simple CustomEvent fired directly by the menu
    //   window.dispatchEvent(new CustomEvent('repweb:open-manage-tabs'))
    const openTabs = () => setShowManageTabs(true);
    window.addEventListener('repweb:open-manage-tabs', openTabs);

    return () => {
      window.removeEventListener('widgets.action', handler);
      window.removeEventListener('repweb:open-manage-tabs', openTabs);
    };
  }, [defs]); // needs defs to add by type

  // Ensure every item has a layout (x/y/w/h) at every breakpoint
  const defsByType = useMemo(() => {
    const m = new Map();
    (defs || []).forEach(d => m.set(d.type, d));
    return m;
  }, [defs]);

  const filledLayouts = useMemo(() => {
    const bps = ['lg', 'md', 'sm', 'xs', 'xxs'];
    const out = {};
    bps.forEach(bp => {
      const arr = Array.isArray(layouts?.[bp]) ? [...layouts[bp]] : [];
      const present = new Set(arr.map(l => l.i));
      items.forEach(item => {
        if (!present.has(item.id)) {
          const def = defsByType.get(item.type) || {};
          arr.push({
            i: item.id,
            x: 0,
            y: Infinity,
            w: def.w ?? 4,
            h: def.h ?? 5,
            minW: def.minW ?? 2,
            minH: def.minH ?? 2,
          });
        }
      });
      out[bp] = arr;
    });
    return out;
  }, [layouts, items, defsByType]);

  if (loading) return <div className="text-muted">Loading…</div>;
  if (err) return <div className="alert alert-danger">{err}</div>;

  // Helper: treat alias types as the map widget
  const isMapType = (t) => ['leaflet', 'worldmap', 'map'].includes(String(t || '').toLowerCase());

  return (
    <div>
      {SHOW_LOCAL_TOOLBAR && (
        <Toolbar
          editing={editing}
          onToggleEdit={() => setEditing(v => !v)}
          onAdd={() => setShowAdd(true)}
          onSave={() => saveCurrent().catch(e => alert(e.message))}
          onReset={reset}
        />
      )}

      <AddWidgetModal
        show={showAdd}
        defs={defs}
        onClose={() => setShowAdd(false)}
        onChoose={(def) => { addWidget(def); setShowAdd(false); }}
      />

      {/* 🔧 NEW: Manage Tabs modal is mounted here */}
      <ManageTabsModal open={showManageTabs} onClose={() => setShowManageTabs(false)} />

      <ResponsiveGridLayout
        className="layout"
        breakpoints={BREAKPOINTS}
        cols={COLS}
        layouts={filledLayouts}
        onLayoutChange={onLayoutsChange}
        isDraggable={editing}
        isResizable={editing}
        margin={[12, 12]}
        containerPadding={[0, 0]}
        rowHeight={12}
        compactType="vertical"
        draggableCancel=".card-body, .widget-close-btn, .no-drag"
      >
        {items.map(item => (
          <div key={item.id}>
            <WidgetChrome
              title={item.title}
              editing={editing}
              onRemove={() => removeWidget(item.id)}
            >
              {/* KPI */}
              {item.type === 'kpi' && (
                <KpiWidget
                  label={item.props?.label ?? 'KPI'}
                  value={item.props?.value ?? 0}
                  sub={item.props?.sub ?? ''}
                />
              )}

              {/* Markdown */}
              {item.type === 'markdown' && (
                <MarkdownWidget md={item.props?.md ?? 'Hello **repweb**!'} />
              )}

              {/* Plotly */}
              {item.type === 'plotly' && (
                <PlotlyWidget
                  reportId={item.props?.reportId ?? 1}
                  height={item.props?.height ?? 360}
                  initialChart={item.props?.chart ?? 'line'}
                  initialAgg={item.props?.agg ?? 'none'}
                />
              )}

              {/* DataTable */}
              {item.type === 'datatable' && (
                <DataTableWidget
                  reportId={item.props?.reportId ?? 1}
                  pageLength={item.props?.pageLength ?? 15}
                />
              )}

              {/* Pivot */}
              {item.type === 'pivot' && (
                <PivotWidget reportId={item.props?.reportId ?? 1} />
              )}

              {/* Grafana */}
              {item.type === 'grafana' && (
                <GrafanaWidget
                  src={item.props?.src ?? 'about:blank'}
                  height={item.props?.height ?? 360}
                />
              )}

              {/* Leaflet Map */}
              {isMapType(item.type) && (
                <LeafletEavMap
                  apiUrl={item.props?.apiUrl ?? '/api/eav/geo/view'}
                  height={item.props?.height ?? '520px'}
                  query={item.props?.query ?? undefined}
                />
              )}

              {/* NiFi */}
              {item.type === 'nifi' && (
                <NiFiWidget
                  title={item.props?.title ?? 'NiFi Process Groups'}
                  refreshSec={item.props?.refreshSec ?? 5}
                />
              )}

              {/* Camera Live (HLS) */}
              {item.type === 'camera-live' && (
                <CameraLiveWidget
                  src={item.props?.src ?? '/hlsdisk/sms18/index.m3u8'}
                  poster={item.props?.poster ?? ''}
                  muted={item.props?.muted ?? true}
                  autoPlay={item.props?.autoPlay ?? true}
                  controls={item.props?.controls ?? true}
                  objectFit={item.props?.objectFit ?? 'cover'}
                  className="w-100 h-100"
                />
              )}

              {/* ✅ CheckMK Health */}
              {item.type === 'checkmk' && (
                <CheckmkWidget
                  apiBase={item.props?.apiBase ?? '/api/checkmk'}
                  refreshMs={item.props?.refreshMs ?? 30000}
                />
              )}

              {/* ✅ Rocket.Chat Realtime */}
              {item.type === 'rocketchat' && (
                <RocketChatLoaderWidget
                  configUrl={item.props?.configUrl ?? '/api/rocketchat/config'}
                  roomName={item.props?.roomName ?? 'repweb'}
                />
              )}

              {/* 🐾 ✅ Caraméow Lucky Numbers */}
              {item.type === 'caralucky' && (
                <CaraLuckyWidget
                  config={{
                    game: item.props?.game ?? 'euromillions'
                  }}
                />
              )}
            </WidgetChrome>
          </div>
        ))}
      </ResponsiveGridLayout>
    </div>
  );
}
