import React, { useEffect, useRef, useState } from 'react';
import Hls from 'hls.js';

export default function CameraLiveWidget({
  src,
  poster = '',
  muted = true,
  autoPlay = true,
  controls = true,
  objectFit = 'cover',
  className = '',
}) {
  const videoRef = useRef(null);
  const hlsRef = useRef(null);
  const [err, setErr] = useState(null);

  useEffect(() => {
    const video = videoRef.current;
    if (!video || !src) return;
    setErr(null);

    let destroyed = false;
    const cleanup = () => {
      if (hlsRef.current) {
        try { hlsRef.current.destroy(); } catch {}
        hlsRef.current = null;
      }
      try { video.removeAttribute('src'); video.load(); } catch {}
    };

    if (video.canPlayType('application/vnd.apple.mpegurl')) {
      // Safari: native HLS
      video.src = src;
      if (autoPlay) video.play().catch(() => {});
    } else if (Hls.isSupported()) {
      const hls = new Hls({ liveDurationInfinity: true });
      hlsRef.current = hls;
      hls.loadSource(src);
      hls.attachMedia(video);
      hls.on(Hls.Events.ERROR, (_evt, data) => {
        if (data.fatal && !destroyed) {
          setErr('Stream error, retrying…');
          try { hls.destroy(); } catch {}
          setTimeout(() => {
            if (destroyed) return;
            const n = new Hls({ liveDurationInfinity: true });
            hlsRef.current = n;
            n.loadSource(src);
            n.attachMedia(video);
          }, 1000);
        }
      });
      video.addEventListener('loadedmetadata', () => {
        if (autoPlay) video.play().catch(() => {});
      }, { once: true });
    } else {
      setErr('HLS not supported in this browser.');
    }

    const onVisibility = () => {
      if (document.hidden) { try { video.pause(); } catch {} }
      else if (autoPlay) { video.play().catch(() => {}); }
    };
    document.addEventListener('visibilitychange', onVisibility);

    return () => {
      destroyed = true;
      document.removeEventListener('visibilitychange', onVisibility);
      cleanup();
    };
  }, [src, autoPlay]);

  return (
    <div className={`rounded-2xl shadow bg-white overflow-hidden ${className}`}>
      <video
        ref={videoRef}
        controls={controls}
        playsInline
        muted={muted}
        poster={poster}
        style={{ width: '100%', height: '100%', objectFit }}
      />
      {err && <div className="p-2 text-danger small">{err}</div>}
    </div>
  );
}
