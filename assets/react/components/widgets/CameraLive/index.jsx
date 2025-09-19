import React from 'react';
import CameraLiveWidget from '@/react/components/CameraLiveWidget.jsx';

export default function CameraLiveRuntime({ options = {}, className = '' }) {
  const {
    src = '/hlsdisk/sms18/index.m3u8',
    poster = '',
    muted = true,
    autoPlay = true,
    controls = true,
    objectFit = 'cover',
  } = options;

  return (
    <div className={className} style={{ width: '100%', height: '100%' }}>
      <CameraLiveWidget
        src={src}
        poster={poster}
        muted={muted}
        autoPlay={autoPlay}
        controls={controls}
        objectFit={objectFit}
        className="w-full h-full"
      />
    </div>
  );
}
