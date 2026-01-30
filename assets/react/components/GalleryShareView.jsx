import React, { useEffect, useState } from 'react';
import axios from 'axios';
import GalleryApp from './GalleryApp.jsx';

export default function GalleryShareView({ apiShare, token }) {
  const [galleryMeta, setGalleryMeta] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const response = await axios.get(apiShare);
        if (!cancelled) {
          setGalleryMeta(response.data?.gallery || null);
        }
      } catch (err) {
        if (!cancelled) {
          setError('Unable to load shared gallery');
        }
      }
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [apiShare]);

  if (error) {
    return <div className="alert alert-danger">{error}</div>;
  }

  if (!galleryMeta) {
    return <div className="text-center py-5">Loading gallery…</div>;
  }

  return (
    <GalleryApp
      apiList={apiShare}
      apiUpload={null}
      apiLayout={null}
      apiItemBase={null}
      readOnly
      onGalleryMeta={setGalleryMeta}
    />
  );
}