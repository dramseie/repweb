import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import GridLayout, { WidthProvider } from 'react-grid-layout';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import GalleryWatermarkModal from './GalleryWatermarkModal.jsx';
import GallerySlideshow from './GallerySlideshow.jsx';

const GalleryGrid = WidthProvider(GridLayout);
const COLS = 12;
const DEFAULT_CELL_WIDTH = 3;
const DEFAULT_CELL_HEIGHT = 3;
const SAVE_DEBOUNCE_MS = 600;

function buildDefaultLayout(order = 0) {
  const perRow = Math.max(1, Math.floor(COLS / DEFAULT_CELL_WIDTH));
  const x = (order % perRow) * DEFAULT_CELL_WIDTH;
  const y = Math.floor(order / perRow) * DEFAULT_CELL_HEIGHT;

  return {
    x,
    y,
    w: DEFAULT_CELL_WIDTH,
    h: DEFAULT_CELL_HEIGHT,
  };
}

function applyLayoutFallback(layout, order) {
  if (!layout || typeof layout !== 'object') {
    return buildDefaultLayout(order);
  }

  const clean = { ...buildDefaultLayout(order) };
  ['x', 'y', 'w', 'h'].forEach((key) => {
    if (key in layout) {
      const value = parseInt(layout[key], 10);
      if (!Number.isNaN(value)) {
        clean[key] = Math.max(0, value);
      }
    }
  });

  if (clean.w <= 0) clean.w = DEFAULT_CELL_WIDTH;
  if (clean.h <= 0) clean.h = DEFAULT_CELL_HEIGHT;

  return clean;
}

function normalisePhoto(apiPhoto) {
  const order = typeof apiPhoto.displayOrder === 'number' ? apiPhoto.displayOrder : 0;
  return {
    id: apiPhoto.id,
    title: apiPhoto.title || '',
    caption: apiPhoto.caption || '',
    originalFilename: apiPhoto.originalFilename || '',
    url: apiPhoto.url,
    previewUrl: apiPhoto.previewUrl || apiPhoto.url,
    mimeType: apiPhoto.mimeType,
    size: apiPhoto.size,
    width: apiPhoto.width,
    height: apiPhoto.height,
    uploadedAt: apiPhoto.uploadedAt,
    updatedAt: apiPhoto.updatedAt,
    displayOrder: order,
    layout: applyLayoutFallback(apiPhoto.layout, order),
    creator: apiPhoto.creator || '',
    metadata: apiPhoto.metadata && typeof apiPhoto.metadata === 'object' ? apiPhoto.metadata : {},
    watermarkOptions: apiPhoto.watermarkOptions && typeof apiPhoto.watermarkOptions === 'object' ? apiPhoto.watermarkOptions : null,
  };
}

export default function GalleryApp({
  apiList,
  apiUpload,
  apiLayout,
  apiItemBase,
  readOnly = false,
  onGalleryMeta,
}) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [isDragging, setDragging] = useState(false);
  const layoutSaveRef = useRef();
  const [uploading, setUploading] = useState(false);
  const [galleryMeta, setGalleryMeta] = useState(null);
  const [activeWatermark, setActiveWatermark] = useState(null);
  const [slideshowIndex, setSlideshowIndex] = useState(null);

  const canUpload = !readOnly && Boolean(apiUpload);
  const canManageLayout = !readOnly && Boolean(apiLayout);
  const canModifyItems = !readOnly && Boolean(apiItemBase);
  const isPublicShare = readOnly && !canUpload && !canManageLayout && !canModifyItems;
  const isSlideshowOpen = typeof slideshowIndex === 'number';

  const apiDelete = useMemo(() => (apiItemBase ? String(apiItemBase) : null), [apiItemBase]);
  const apiUpdate = useMemo(() => (apiItemBase ? String(apiItemBase) : null), [apiItemBase]);
  const apiWatermarkBase = apiUpdate;

  const fetchItems = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await axios.get(apiList);
      const data = Array.isArray(response.data?.data) ? response.data.data : [];
      const meta = response.data?.gallery || null;
      setGalleryMeta(meta);
      if (onGalleryMeta) {
        onGalleryMeta(meta);
      }
      const mapped = data.map(normalisePhoto);
      setItems(mapped);
    } catch (err) {
      setError('Unable to load gallery');
    } finally {
      setLoading(false);
    }
  }, [apiList, onGalleryMeta]);

  useEffect(() => {
    fetchItems();
  }, [fetchItems]);

  useEffect(() => () => {
    if (layoutSaveRef.current) {
      clearTimeout(layoutSaveRef.current);
    }
  }, []);

  useEffect(() => {
    if (typeof document === 'undefined' || !isSlideshowOpen) {
      return undefined;
    }
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [isSlideshowOpen]);

  useEffect(() => {
    if (!isSlideshowOpen || items.length > 0) {
      return;
    }
    setSlideshowIndex(null);
  }, [isSlideshowOpen, items.length]);

  const uploadFiles = useCallback(async (fileList) => {
    if (!canUpload) {
      return;
    }
    setUploading(true);
    try {
      const formData = new FormData();
      fileList.forEach((file) => formData.append('files[]', file, file.name));

      const response = await axios.post(apiUpload, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      const returned = Array.isArray(response.data?.data) ? response.data.data : [];
      if (returned.length > 0) {
        setItems((current) => {
          const existingIds = new Set(current.map((item) => item.id));
          const mapped = returned
            .map(normalisePhoto)
            .filter((item) => !existingIds.has(item.id));
          return [...current, ...mapped];
        });
      }
    } catch (err) {
      setError('Upload failed');
    } finally {
      setUploading(false);
    }
  }, [apiUpload, canUpload]);

  const handleDragOver = useCallback((event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
    setDragging(true);
  }, []);

  const handleDragLeave = useCallback((event) => {
    event.preventDefault();
    setDragging(false);
  }, []);

  const handleDrop = useCallback((event) => {
    event.preventDefault();
    setDragging(false);
    if (!canUpload) {
      return;
    }
    const droppedFiles = Array.from(event.dataTransfer.files || []);
    if (droppedFiles.length > 0) {
      void uploadFiles(droppedFiles);
    }
  }, [canUpload, uploadFiles]);

  const handleFileInput = useCallback((event) => {
    const selectedFiles = Array.from(event.target.files || []);
    if (selectedFiles.length > 0) {
      void uploadFiles(selectedFiles);
    }
    event.target.value = '';
  }, [uploadFiles]);

  const scheduleLayoutSave = useCallback((payload) => {
    if (layoutSaveRef.current) {
      clearTimeout(layoutSaveRef.current);
    }
    layoutSaveRef.current = setTimeout(async () => {
      if (!apiLayout || readOnly) {
        return;
      }
      try {
        await axios.patch(apiLayout, { items: payload });
      } catch (err) {
        setError('Failed to save layout');
      }
    }, SAVE_DEBOUNCE_MS);
  }, [apiLayout, readOnly]);

  const handleLayoutChange = useCallback((nextLayout) => {
    if (!canManageLayout) {
      return;
    }
    setItems((current) => {
      const byId = new Map(current.map((item) => [String(item.id), item]));
      const updatedItems = nextLayout.map((entry, index) => {
        const currentItem = byId.get(entry.i);
        if (!currentItem) {
          return null;
        }
        const nextItem = {
          ...currentItem,
          displayOrder: index,
          layout: {
            x: entry.x,
            y: entry.y,
            w: entry.w,
            h: entry.h,
          },
        };
        return nextItem;
      }).filter(Boolean);

      const payload = updatedItems.map((item) => ({
        id: item.id,
        order: item.displayOrder,
        layout: item.layout,
      }));

      scheduleLayoutSave(payload);
      return updatedItems;
    });
  }, [canManageLayout, scheduleLayoutSave]);

  const handleRemove = useCallback(async (id) => {
    if (!canModifyItems || !apiDelete) {
      return;
    }
    if (!window.confirm('Remove this photo?')) {
      return;
    }
    try {
      await axios.delete(`${apiDelete}${id}`);
      setItems((current) => current.filter((item) => item.id !== id));
    } catch (err) {
      setError('Failed to remove photo');
    }
  }, [apiDelete, canModifyItems]);

  const handleEdit = useCallback(async (item) => {
    if (!canModifyItems || !apiUpdate) {
      return;
    }
    const title = window.prompt('Photo title', item.title || '') ?? undefined;
    if (title === undefined) {
      return;
    }
    const caption = window.prompt('Photo caption', item.caption || '') ?? undefined;
    if (caption === undefined) {
      return;
    }

    try {
      const response = await axios.patch(`${apiUpdate}${item.id}`, {
        title,
        caption,
      });
      const updated = response.data?.data ? normalisePhoto(response.data.data) : null;
      if (updated) {
        setItems((current) => current.map((entry) => (entry.id === updated.id ? { ...entry, ...updated } : entry)));
      }
    } catch (err) {
      setError('Failed to update photo');
    }
  }, [apiUpdate, canModifyItems]);

  const handleOpenWatermark = useCallback((item) => {
    setActiveWatermark(item);
  }, [setActiveWatermark]);

  const handleWatermarkClose = useCallback(() => {
    setActiveWatermark(null);
  }, [setActiveWatermark]);

  const handleWatermarkSaved = useCallback((updated) => {
    if (!updated) {
      setActiveWatermark(null);
      return;
    }
    const mapped = normalisePhoto(updated);
    setItems((current) => current.map((entry) => (entry.id === mapped.id ? mapped : entry)));
    setActiveWatermark(null);
  }, [setActiveWatermark, setItems]);

  const openSlideshow = useCallback((index = 0) => {
    if (!isPublicShare || items.length === 0) {
      return;
    }
    const bounded = Math.min(Math.max(index, 0), items.length - 1);
    setSlideshowIndex(bounded);
  }, [isPublicShare, items.length]);

  const closeSlideshow = useCallback(() => {
    setSlideshowIndex(null);
  }, []);

  const handleShareItemClick = useCallback((event, index) => {
    if (!isPublicShare) {
      return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    if (typeof event.button === 'number' && event.button !== 0) {
      return;
    }
    event.preventDefault();
    openSlideshow(index);
  }, [isPublicShare, openSlideshow]);

  const layout = useMemo(() => items.map((item) => ({
    i: String(item.id),
    x: item.layout.x,
    y: item.layout.y,
    w: item.layout.w,
    h: item.layout.h,
  })), [items]);

  const dropzoneClass = useMemo(() => {
    const base = 'gallery-dropzone card border-dashed mb-4';
    return isDragging ? `${base} is-active` : base;
  }, [isDragging]);

  const emptyState = !loading && items.length === 0 && !error;

  return (
    <div className="gallery-app">
      {galleryMeta && (
        <div className={`mb-3${isPublicShare ? ' gallery-share-header' : ''}`}>
          <div className="gallery-share-header-text">
            <h2 className="h4 mb-1">{galleryMeta.name}</h2>
            {galleryMeta.shareUrl && (
              <p className="text-muted small mb-0">Share link: {galleryMeta.shareUrl}</p>
            )}
          </div>
          {isPublicShare && items.length > 0 && (
            <div className="gallery-share-header-actions">
              <button
                type="button"
                className="btn btn-primary gallery-share-presentation-btn"
                onClick={() => openSlideshow(0)}
              >
                Start presentation
              </button>
            </div>
          )}
        </div>
      )}

      {canUpload ? (
        <div
          className={dropzoneClass}
          onDragOver={handleDragOver}
          onDragEnter={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
        >
          <div className="card-body text-center">
            <p className="mb-2">Drag &amp; drop photos here or</p>
            <label className="btn btn-primary">
              Select files
              <input type="file" accept="image/*" multiple onChange={handleFileInput} hidden />
            </label>
            {uploading && <p className="mt-3 text-muted">Uploading…</p>}
          </div>
        </div>
      ) : (
        <div className="alert alert-secondary">Viewing shared gallery</div>
      )}

      {error && <div className="alert alert-danger">{error}</div>}

      {emptyState && (
        <div className="alert alert-info">No photos yet. Drag and drop images to get started.</div>
      )}

      {loading && <div className="text-center py-5">Loading gallery…</div>}

      {!loading && !emptyState && (
        isPublicShare ? (
          <div className="gallery-share-grid">
            {items.map((item, index) => {
              const fallbackTitle = item.title || 'Untitled photo';
              const altText = item.title || item.originalFilename || `Photo ${item.id}`;
              return (
                <article key={item.id} className="gallery-item gallery-item-share">
                  <header className="gallery-item-share-header">
                    <h2 className="gallery-item-share-title">{fallbackTitle}</h2>
                    {item.caption && <p className="gallery-item-share-caption">{item.caption}</p>}
                  </header>
                  <a
                    className="gallery-item-share-media"
                    href={item.url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={`Open ${fallbackTitle}`}
                    onClick={(event) => handleShareItemClick(event, index)}
                  >
                    <img src={item.previewUrl} alt={altText} />
                  </a>
                </article>
              );
            })}
          </div>
        ) : (
          <GalleryGrid
            className="gallery-grid"
            cols={COLS}
            rowHeight={120}
            layout={layout}
            margin={[16, 16]}
            onLayoutChange={handleLayoutChange}
            isDraggable={!readOnly}
            isResizable={!readOnly}
            draggableCancel=".gallery-item button, .gallery-item a, .gallery-item input, .gallery-item textarea"
            compactType={null}
            preventCollision
          >
            {items.map((item) => {
              const fallbackTitle = item.title || 'Untitled photo';
              const altText = item.title || item.originalFilename || `Photo ${item.id}`;
              return (
                <div key={item.id} className="gallery-item card">
                  <div className="gallery-item-image-wrapper">
                    <img src={item.previewUrl} alt={altText} />
                  </div>
                  <div className="card-body p-3">
                    <div className="d-flex justify-content-between align-items-start">
                      <div>
                        <h2 className="h6 mb-1">{fallbackTitle}</h2>
                        {item.caption && <p className="text-muted mb-1 small">{item.caption}</p>}
                      </div>
                      <div className="btn-group btn-group-sm">
                        {canModifyItems && (
                          <>
                            <button type="button" className="btn btn-outline-secondary" onClick={() => handleEdit(item)}>Edit</button>
                            <button type="button" className="btn btn-outline-secondary" onClick={() => handleOpenWatermark(item)}>Watermark</button>
                          </>
                        )}
                        <a className="btn btn-outline-secondary" href={item.url} target="_blank" rel="noreferrer">Open</a>
                        {canModifyItems && (
                          <button type="button" className="btn btn-outline-danger" onClick={() => handleRemove(item.id)}>Delete</button>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </GalleryGrid>
        )
      )}

      {activeWatermark && apiWatermarkBase && (
        <GalleryWatermarkModal
          photo={activeWatermark}
          apiEndpoint={`${apiWatermarkBase}${activeWatermark.id}/watermark`}
          onClose={handleWatermarkClose}
          onSaved={handleWatermarkSaved}
        />
      )}

      {isPublicShare && isSlideshowOpen && (
        <GallerySlideshow
          items={items}
          initialIndex={slideshowIndex}
          onClose={closeSlideshow}
        />
      )}
    </div>
  );
}
