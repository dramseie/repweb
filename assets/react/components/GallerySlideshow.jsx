import React, { useCallback, useEffect, useMemo, useState } from 'react';

const AUTO_ADVANCE_MS = 6000;

function normalise(items) {
  return items.map((item, index) => ({
    id: item.id ?? index,
    url: item.url,
    title: item.title && item.title.trim().length > 0 ? item.title : 'Untitled photo',
    caption: item.caption || '',
    alt: item.title || item.originalFilename || `Photo ${item.id ?? index}`,
  })).filter((entry) => typeof entry.url === 'string' && entry.url.length > 0);
}

export default function GallerySlideshow({ items, initialIndex = 0, onClose }) {
  const slides = useMemo(() => normalise(items || []), [items]);
  const total = slides.length;
  const safeInitial = useMemo(() => {
    if (total === 0) {
      return 0;
    }
    if (initialIndex < 0) {
      return 0;
    }
    if (initialIndex >= total) {
      return total - 1;
    }
    return initialIndex;
  }, [initialIndex, total]);

  const [activeIndex, setActiveIndex] = useState(safeInitial);

  useEffect(() => {
    setActiveIndex(safeInitial);
  }, [safeInitial]);

  const handleClose = useCallback(() => {
    if (onClose) {
      onClose();
    }
  }, [onClose]);

  const handleNext = useCallback(() => {
    if (total <= 1) {
      return;
    }
    setActiveIndex((current) => (current + 1) % total);
  }, [total]);

  const handlePrev = useCallback(() => {
    if (total <= 1) {
      return;
    }
    setActiveIndex((current) => ((current - 1 + total) % total));
  }, [total]);

  useEffect(() => {
    if (total <= 1) {
      return undefined;
    }
    const timer = setTimeout(() => {
      setActiveIndex((current) => (current + 1) % total);
    }, AUTO_ADVANCE_MS);
    return () => clearTimeout(timer);
  }, [activeIndex, total]);

  useEffect(() => {
    const handleKey = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        handleClose();
      }
      if (event.key === 'ArrowRight') {
        event.preventDefault();
        handleNext();
      }
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        handlePrev();
      }
    };

    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [handleClose, handleNext, handlePrev]);

  const handleOverlayClick = useCallback((event) => {
    if (event.target === event.currentTarget) {
      handleClose();
    }
  }, [handleClose]);

  if (total === 0) {
    return null;
  }

  const current = slides[activeIndex] || slides[0];
  const progressPercent = total > 0 ? ((activeIndex + 1) / total) * 100 : 0;

  return (
    <div className="gallery-slideshow-overlay" role="dialog" aria-modal="true" onClick={handleOverlayClick}>
      <button
        type="button"
        className="gallery-slideshow-close"
        aria-label="Close presentation"
        onClick={handleClose}
      >
        <span aria-hidden="true">&times;</span>
        <span className="visually-hidden">Close</span>
      </button>

      {total > 1 && (
        <button
          type="button"
          className="gallery-slideshow-nav gallery-slideshow-nav--prev"
          aria-label="Previous photo"
          onClick={handlePrev}
        >
          <span aria-hidden="true">&lsaquo;</span>
          <span className="visually-hidden">Previous</span>
        </button>
      )}

      <div className="gallery-slideshow-frame">
        <div className="gallery-slideshow-image-wrapper">
          <img
            key={`${current.id}-${activeIndex}`}
            src={current.url}
            alt={current.alt}
            className="gallery-slideshow-image"
          />
        </div>
        <footer className="gallery-slideshow-meta">
          <div className="gallery-slideshow-meta-text">
            <h3 className="gallery-slideshow-title">{current.title}</h3>
            {current.caption && <p className="gallery-slideshow-caption">{current.caption}</p>}
          </div>
          <div className="gallery-slideshow-meta-aside">
            <span className="gallery-slideshow-progress-count">{activeIndex + 1} / {total}</span>
            <div className="gallery-slideshow-progress-bar">
              <span style={{ width: `${progressPercent}%` }} />
            </div>
          </div>
        </footer>
      </div>

      {total > 1 && (
        <button
          type="button"
          className="gallery-slideshow-nav gallery-slideshow-nav--next"
          aria-label="Next photo"
          onClick={handleNext}
        >
          <span aria-hidden="true">&rsaquo;</span>
          <span className="visually-hidden">Next</span>
        </button>
      )}
    </div>
  );
}
