import React, { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import GalleryApp from './GalleryApp.jsx';

export default function GalleryDashboard({ apiBase, initialSlug = '' }) {
  const [galleries, setGalleries] = useState([]);
  const [selectedSlug, setSelectedSlug] = useState(initialSlug);
  const [galleryMeta, setGalleryMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [shareCopied, setShareCopied] = useState(false);
  const [creating, setCreating] = useState(false);
  const [refreshingShare, setRefreshingShare] = useState(false);

  const loadGalleries = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await axios.get(apiBase);
      const data = Array.isArray(response.data?.data) ? response.data.data : [];
      setGalleries(data);
      if (!selectedSlug && data.length > 0) {
        setSelectedSlug(data[0].slug);
      }
    } catch (err) {
      setError('Unable to load galleries');
    } finally {
      setLoading(false);
    }
  }, [apiBase, selectedSlug]);

  useEffect(() => {
    void loadGalleries();
  }, [loadGalleries]);

  const selectedGallery = useMemo(
    () => galleries.find((g) => g.slug === selectedSlug) || null,
    [galleries, selectedSlug]
  );

  useEffect(() => {
    if (!selectedSlug && galleries.length > 0) {
      setSelectedSlug(galleries[0].slug);
    }
  }, [galleries, selectedSlug]);

  const handleCreateGallery = useCallback(async () => {
    const name = window.prompt('Gallery name');
    if (!name) {
      return;
    }
    setCreating(true);
    setError('');
    try {
      const response = await axios.post(apiBase, { name });
      const created = response.data?.data || null;
      if (created) {
        await loadGalleries();
        setSelectedSlug(created.slug);
      } else {
        await loadGalleries();
      }
    } catch (err) {
      setError('Failed to create gallery');
    } finally {
      setCreating(false);
    }
  }, [apiBase, loadGalleries]);

  const handleRefreshShare = useCallback(async () => {
    if (!selectedSlug) {
      return;
    }
    setRefreshingShare(true);
    setError('');
    try {
      const response = await axios.post(`${apiBase}/${selectedSlug}/share`);
      const updated = response.data?.data || null;
      if (updated) {
        setGalleryMeta(updated);
        setGalleries((prev) => prev.map((g) => (g.slug === updated.slug ? updated : g)));
      }
    } catch (err) {
      setError('Failed to refresh share link');
    } finally {
      setRefreshingShare(false);
    }
  }, [apiBase, selectedSlug]);

  const handleCopyShare = useCallback(async () => {
    const shareUrl = galleryMeta?.shareUrl;
    if (!shareUrl) {
      return;
    }
    try {
      await navigator.clipboard.writeText(shareUrl);
      setShareCopied(true);
      setTimeout(() => setShareCopied(false), 2000);
    } catch (err) {
      setError('Unable to copy link');
    }
  }, [galleryMeta]);

  const listEndpoint = selectedSlug ? `${apiBase}/${selectedSlug}/photos` : null;
  const uploadEndpoint = selectedSlug ? `${apiBase}/${selectedSlug}/photos` : null;
  const layoutEndpoint = selectedSlug ? `${apiBase}/${selectedSlug}/photos/layout` : null;
  const itemBase = selectedSlug ? `${apiBase}/${selectedSlug}/photos/` : null;

  return (
    <div className="gallery-dashboard">
      <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div className="d-flex align-items-center gap-2">
          <label className="form-label mb-0" htmlFor="gallery-selector">Gallery</label>
          <select
            id="gallery-selector"
            className="form-select"
            style={{ minWidth: '200px' }}
            value={selectedSlug || ''}
            onChange={(e) => setSelectedSlug(e.target.value)}
            disabled={loading || galleries.length === 0}
          >
            {galleries.map((gallery) => (
              <option key={gallery.slug} value={gallery.slug}>
                {gallery.name}
              </option>
            ))}
          </select>
          <button
            type="button"
            className="btn btn-outline-primary"
            onClick={handleCreateGallery}
            disabled={creating}
          >
            {creating ? 'Creating…' : 'New gallery'}
          </button>
        </div>
        <div className="d-flex align-items-center gap-2 flex-wrap">
          <span className="fw-semibold">Share link:</span>
          <span className="text-truncate" style={{ maxWidth: '280px' }}>
            {galleryMeta?.shareUrl || 'Select a gallery'}
          </span>
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm"
            onClick={handleCopyShare}
            disabled={!galleryMeta?.shareUrl}
          >
            {shareCopied ? 'Copied!' : 'Copy link'}
          </button>
          <button
            type="button"
            className="btn btn-outline-danger btn-sm"
            onClick={handleRefreshShare}
            disabled={refreshingShare || !selectedSlug}
          >
            {refreshingShare ? 'Refreshing…' : 'Regenerate link'}
          </button>
        </div>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {loading && <div className="text-center py-5">Loading galleries…</div>}
      {!loading && selectedSlug && listEndpoint && (
        <GalleryApp
          key={selectedSlug}
          apiList={listEndpoint}
          apiUpload={uploadEndpoint}
          apiLayout={layoutEndpoint}
          apiItemBase={itemBase}
          readOnly={false}
          onGalleryMeta={setGalleryMeta}
        />
      )}
      {!loading && !selectedSlug && (
        <div className="alert alert-info">Create a gallery to get started.</div>
      )}
    </div>
  );
}
