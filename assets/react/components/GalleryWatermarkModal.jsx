import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';

const modalBackdropStyle = {
  position: 'fixed',
  inset: 0,
  backgroundColor: 'rgba(0, 0, 0, 0.5)',
  zIndex: 1050,
};

const modalDialogStyle = {
  position: 'fixed',
  top: '50%',
  left: '50%',
  transform: 'translate(-50%, -50%)',
  zIndex: 1051,
  width: 'min(900px, 95vw)',
  maxHeight: '90vh',
  overflow: 'hidden',
};

const modalBodyStyle = {
  overflowY: 'auto',
  maxHeight: 'calc(90vh - 120px)',
};

export default function GalleryWatermarkModal({ photo, apiEndpoint, onClose, onSaved }) {
  const containerRef = useRef(null);
  const [containerSize, setContainerSize] = useState({ width: 0, height: 0 });
  const [position, setPosition] = useState(() => {
    const opts = photo.watermarkOptions || {};
    return {
      x: typeof opts.x === 'number' ? Math.min(Math.max(opts.x, 0), 1) : 0.5,
      y: typeof opts.y === 'number' ? Math.min(Math.max(opts.y, 0), 1) : 0.5,
    };
  });
  const [scale, setScale] = useState(() => {
    const opts = photo.watermarkOptions || {};
    return typeof opts.scale === 'number' ? Math.min(Math.max(opts.scale, 0.05), 1) : 0.25;
  });
  const [opacity, setOpacity] = useState(() => {
    const opts = photo.watermarkOptions || {};
    return typeof opts.opacity === 'number' ? Math.min(Math.max(opts.opacity, 0), 1) : 1;
  });
  const [logoFile, setLogoFile] = useState(null);
  const [logoPreview, setLogoPreview] = useState(null);
  const [logoSize, setLogoSize] = useState({ width: 1, height: 1 });
  const [dragging, setDragging] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [creator, setCreator] = useState(photo.creator || '');
  const [copyrightText, setCopyrightText] = useState(photo.metadata?.copyright || '');
  const [description, setDescription] = useState(photo.metadata?.description || '');

  const logoAspect = useMemo(() => {
    const width = logoSize.width || 1;
    const height = logoSize.height || 1;
    return height / width;
  }, [logoSize]);

  useEffect(() => {
    const node = containerRef.current;
    if (!node) {
      return undefined;
    }

    const updateFromRect = () => {
      const rect = node.getBoundingClientRect();
      setContainerSize({ width: rect.width, height: rect.height });
    };

    if (typeof ResizeObserver === 'undefined') {
      updateFromRect();
      return undefined;
    }

    const observer = new ResizeObserver((entries) => {
      const entry = entries[0];
      if (!entry) {
        return;
      }
      const rect = entry.contentRect;
      setContainerSize({ width: rect.width, height: rect.height });
    });

    updateFromRect();
    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    if (!logoFile) {
      setLogoPreview(null);
      setLogoSize({ width: 1, height: 1 });
      return undefined;
    }

    const objectUrl = URL.createObjectURL(logoFile);
    setLogoPreview(objectUrl);

    const img = new Image();
    img.onload = () => {
      setLogoSize({ width: img.naturalWidth || 1, height: img.naturalHeight || 1 });
    };
    img.src = objectUrl;

    return () => {
      URL.revokeObjectURL(objectUrl);
    };
  }, [logoFile]);

  const clampPosition = useCallback((current) => {
    const overlayWidth = containerSize.width * Math.min(Math.max(scale, 0.05), 1);
    const overlayHeight = overlayWidth * logoAspect;
    const maxX = Math.max(containerSize.width - overlayWidth, 0);
    const maxY = Math.max(containerSize.height - overlayHeight, 0);

    const adjustedX = maxX > 0 ? Math.min(Math.max(current.x, 0), 1) : 0;
    const adjustedY = maxY > 0 ? Math.min(Math.max(current.y, 0), 1) : 0;

    return { x: adjustedX, y: adjustedY };
  }, [containerSize, logoAspect, scale]);

  useEffect(() => {
    setPosition((prev) => clampPosition(prev));
  }, [clampPosition]);

  const overlayMetrics = useMemo(() => {
    const widthPx = containerSize.width * Math.min(Math.max(scale, 0.05), 1);
    const heightPx = widthPx * logoAspect;
    const maxX = Math.max(containerSize.width - widthPx, 0);
    const maxY = Math.max(containerSize.height - heightPx, 0);
    const leftPx = maxX * Math.min(Math.max(position.x, 0), 1);
    const topPx = maxY * Math.min(Math.max(position.y, 0), 1);

    return {
      widthPx,
      heightPx,
      leftPx,
      topPx,
      maxX,
      maxY,
    };
  }, [containerSize, logoAspect, position, scale]);

  const updatePositionFromEvent = useCallback((event) => {
    if (!containerRef.current) {
      return;
    }
    const rect = containerRef.current.getBoundingClientRect();
    const overlayWidth = overlayMetrics.widthPx;
    const overlayHeight = overlayMetrics.heightPx;
    const maxX = Math.max(rect.width - overlayWidth, 0);
    const maxY = Math.max(rect.height - overlayHeight, 0);

    const rawX = event.clientX - rect.left - overlayWidth / 2;
    const rawY = event.clientY - rect.top - overlayHeight / 2;

    const clampedX = Math.min(Math.max(rawX, 0), maxX);
    const clampedY = Math.min(Math.max(rawY, 0), maxY);

    setPosition({
      x: maxX > 0 ? clampedX / maxX : 0,
      y: maxY > 0 ? clampedY / maxY : 0,
    });
  }, [overlayMetrics.heightPx, overlayMetrics.widthPx]);

  const handleDragStart = useCallback((event) => {
    event.preventDefault();
    if (!logoPreview) {
      return;
    }
    setDragging(true);
    updatePositionFromEvent(event);
  }, [logoPreview, updatePositionFromEvent]);

  const handleDragMove = useCallback((event) => {
    if (!dragging) {
      return;
    }
    event.preventDefault();
    updatePositionFromEvent(event);
  }, [dragging, updatePositionFromEvent]);

  const handleDragEnd = useCallback(() => {
    setDragging(false);
  }, []);

  useEffect(() => {
    if (!dragging) {
      return undefined;
    }
    const handlePointerMove = (event) => handleDragMove(event);
    const handlePointerUp = () => handleDragEnd();
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', handlePointerUp, { once: true });
    return () => {
      window.removeEventListener('pointermove', handlePointerMove);
      window.removeEventListener('pointerup', handlePointerUp);
    };
  }, [dragging, handleDragEnd, handleDragMove]);

  const handleFileInput = useCallback((event) => {
    const file = event.target.files?.[0] || null;
    setLogoFile(file);
    event.target.value = '';
  }, []);

  const handleSubmit = useCallback(async () => {
    if (!logoFile || saving) {
      return;
    }
    setSaving(true);
    setError('');

    try {
      const formData = new FormData();
      formData.append('logo', logoFile);
      formData.append('x', position.x.toFixed(4));
      formData.append('y', position.y.toFixed(4));
      formData.append('scale', scale.toFixed(4));
      formData.append('opacity', opacity.toFixed(2));
      formData.append('creator', creator);
      formData.append('metadata[description]', description);
      formData.append('metadata[copyright]', copyrightText);

      const response = await axios.post(apiEndpoint, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      const updated = response.data?.data || null;
      onSaved(updated);
    } catch (err) {
      setError('Failed to apply watermark');
    } finally {
      setSaving(false);
    }
  }, [apiEndpoint, creator, description, copyrightText, logoFile, onSaved, opacity, position.x, position.y, saving, scale]);

  const modalOverlayStyle = useMemo(() => ({
    position: 'absolute',
    left: `${overlayMetrics.leftPx}px`,
    top: `${overlayMetrics.topPx}px`,
    width: `${overlayMetrics.widthPx}px`,
    height: `${overlayMetrics.heightPx}px`,
    cursor: logoPreview ? 'move' : 'not-allowed',
    opacity: Math.max(0.1, opacity),
    userSelect: 'none',
    pointerEvents: logoPreview ? 'auto' : 'none',
  }), [overlayMetrics.heightPx, overlayMetrics.leftPx, overlayMetrics.topPx, overlayMetrics.widthPx, logoPreview, opacity]);

  return (
    <>
      <div style={modalBackdropStyle} onClick={onClose} />
      <div className="card shadow-lg" style={modalDialogStyle} role="dialog" aria-modal="true">
        <div className="card-header d-flex align-items-center justify-content-between">
          <h2 className="h5 mb-0">Add watermark</h2>
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={onClose}>
            Close
          </button>
        </div>
        <div className="card-body" style={modalBodyStyle}>
          <div className="row g-4">
            <div className="col-12 col-lg-7">
              <div
                ref={containerRef}
                className="position-relative border rounded overflow-hidden"
                style={{ minHeight: '320px', backgroundColor: '#f8f9fa' }}
              >
                <img
                  src={photo.previewUrl || photo.url}
                  alt={photo.title || 'Gallery photo'}
                  className="img-fluid d-block"
                  style={{ width: '100%', height: 'auto', display: 'block' }}
                />
                {logoPreview && (
                  <img
                    src={logoPreview}
                    alt="Watermark preview"
                    style={modalOverlayStyle}
                    onPointerDown={handleDragStart}
                    draggable={false}
                  />
                )}
                {!logoPreview && (
                  <div className="position-absolute top-50 start-50 translate-middle text-muted text-center">
                    <p className="mb-0">Select a logo to begin</p>
                  </div>
                )}
              </div>
              <div className="mt-3">
                <label className="form-label">Logo image</label>
                <input type="file" accept="image/*" className="form-control" onChange={handleFileInput} />
                <div className="form-text">Upload a transparent PNG for best results. Watermarked images are saved as JPEG.</div>
              </div>
              <div className="mt-3 row g-3">
                <div className="col-12 col-md-6">
                  <label className="form-label">Size</label>
                  <input
                    type="range"
                    min="0.05"
                    max="0.9"
                    step="0.01"
                    value={scale}
                    onChange={(event) => setScale(parseFloat(event.target.value) || 0.05)}
                    className="form-range"
                  />
                </div>
                <div className="col-12 col-md-6">
                  <label className="form-label">Opacity</label>
                  <input
                    type="range"
                    min="0.1"
                    max="1"
                    step="0.05"
                    value={opacity}
                    onChange={(event) => setOpacity(parseFloat(event.target.value) || 1)}
                    className="form-range"
                  />
                </div>
              </div>
            </div>
            <div className="col-12 col-lg-5">
              <div className="mb-3">
                <label className="form-label">Creator</label>
                <input
                  type="text"
                  className="form-control"
                  value={creator}
                  onChange={(event) => setCreator(event.target.value)}
                  placeholder="Name of creator"
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Description</label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={description}
                  onChange={(event) => setDescription(event.target.value)}
                  placeholder="Describe the image or watermark"
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Copyright notice</label>
                <input
                  type="text"
                  className="form-control"
                  value={copyrightText}
                  onChange={(event) => setCopyrightText(event.target.value)}
                  placeholder="© Company Name"
                />
              </div>
              {error && <div className="alert alert-danger">{error}</div>}
              <div className="d-flex justify-content-end gap-2">
                <button type="button" className="btn btn-outline-secondary" onClick={onClose} disabled={saving}>
                  Cancel
                </button>
                <button
                  type="button"
                  className="btn btn-primary"
                  onClick={handleSubmit}
                  disabled={!logoFile || saving}
                >
                  {saving ? 'Saving...' : 'Apply watermark'}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
