import React, { useCallback, useEffect, useMemo, useState } from 'react';
import GridLayout from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import { Api } from './api';
import TileCard from './TileCard';
import AddTileModal from './AddTileModal';

// Default grid config
const COLS = 12;
const ROW_HEIGHT = 30; // px per row

function toLayout(userTile, idx) {
  const l = userTile.layout || {};
  const w = Math.max(3, Math.min(6, parseInt(l.w ?? 4, 10)));
  const h = Math.max(4, Math.min(12, parseInt(l.h ?? 6, 10)));
  const x = Math.max(0, Math.min(COLS - w, parseInt(l.x ?? ((idx * 4) % COLS), 10)));
  const y = l.y !== undefined ? parseInt(l.y, 10) : Infinity; // put at bottom if unset
  return { i: String(userTile.id), x, y, w, h, minW: 3, minH: 3 };
}

/**
 * Props:
 * - showHeader?: boolean (default false)
 * - headerTitle?: string (default "My Reports")
 * - externalAddButtonId?: string (id of an external button that should open the modal)
 */
export default function TilesDashboard({
  showHeader = false,
  headerTitle = 'My Reports',
  externalAddButtonId,
}) {
  const [allTiles, setAllTiles] = useState([]);
  const [myTiles, setMyTiles] = useState([]);
  const [layout, setLayout] = useState([]);
  const [gridWidth, setGridWidth] = useState(1200);
  const [loading, setLoading] = useState(true);
  const [showAdd, setShowAdd] = useState(false);
  const [error, setError] = useState(null);

  // Make the grid width match the container
  useEffect(() => {
    const el = document.getElementById('react-tiles-dashboard');
    const update = () => setGridWidth((el && el.clientWidth) ? el.clientWidth : 1200);
    update();
    window.addEventListener('resize', update);
    return () => window.removeEventListener('resize', update);
  }, []);

  // Optionally wire an external "Add tiles" button (e.g., from Twig)
  useEffect(() => {
    if (!externalAddButtonId) return;
    const btn = document.getElementById(externalAddButtonId);
    if (!btn) return;
    const handler = () => setShowAdd(true);
    btn.addEventListener('click', handler);
    return () => btn.removeEventListener('click', handler);
  }, [externalAddButtonId]);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const [a, m] = await Promise.all([Api.getAllTiles(), Api.getMyTiles()]);
      setAllTiles(a);
      setMyTiles(m);
      setLayout(m.map(toLayout));
      setError(null);
    } catch (e) {
      setError(String(e.message || e));
    }
    setLoading(false);
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  async function addTile(tileId) {
    try {
      await Api.addUserTile(tileId);
      await refresh();
      setShowAdd(false);
    } catch (e) { alert(e.message || e); }
  }

  async function removeTile(userTileId) {
    if (!confirm('Remove this tile?')) return;
    try {
      await Api.removeUserTile(userTileId);
      setMyTiles(prev => prev.filter(x => x.id !== userTileId));
      setLayout(prev => prev.filter(l => l.i !== String(userTileId)));
    } catch (e) { alert(e.message || e); }
  }

  const onLayoutChange = useCallback(async (newLayout) => {
    setLayout(newLayout);
    try {
      await Promise.all(newLayout.map(l => {
        const id = parseInt(l.i, 10);
        return Api.updateLayout(id, { x: l.x, y: l.y, w: l.w, h: l.h });
      }));
    } catch (e) {
      console.warn('Layout save failed', e);
    }
  }, []);

  const sorted = useMemo(
    () => [...myTiles].sort((a, b) => (b.pinned - a.pinned) || (a.position - b.position)),
    [myTiles]
  );

  if (loading) return <div>Loading…</div>;
  if (error) return <div className="alert alert-danger">{error}</div>;

  const layoutMap = new Map(layout.map(l => [l.i, l]));

  return (
    <>
      {showHeader && (
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h1 className="h3 mb-0">{headerTitle}</h1>
          <button className="btn btn-primary" onClick={() => setShowAdd(true)}>
            <i className="bi bi-plus-circle me-1" /> Add tiles
          </button>
        </div>
      )}

      <GridLayout
        className="layout"
        cols={COLS}
        rowHeight={ROW_HEIGHT}
        width={gridWidth}
        isResizable
        isDraggable
        onLayoutChange={onLayoutChange}
        layout={sorted.map((ut, idx) => layoutMap.get(String(ut.id)) || toLayout(ut, idx))}
        compactType="vertical"
        margin={[16, 16]}
        containerPadding={[0, 0]}
      >
        {sorted.map((ut, idx) => (
          <div key={String(ut.id)} data-grid={layoutMap.get(String(ut.id)) || toLayout(ut, idx)}>
            <TileCard userTile={ut} onRemove={removeTile} />
          </div>
        ))}
      </GridLayout>

      {showAdd && (
        <AddTileModal
          allTiles={allTiles}
          myTiles={myTiles}
          onAdd={addTile}
          onClose={() => setShowAdd(false)}
        />
      )}
    </>
  );
}
