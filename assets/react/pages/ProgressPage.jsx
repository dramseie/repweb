import React from 'react';
import EndpointHealthPanel from '../components/EndpointHealthPanel';
import ProgressHUD from '../components/ProgressHUD';

export default function ProgressPage() {
  return (
    <div className="container py-3">
      <EndpointHealthPanel />
      <div className="mt-3">
        <ProgressHUD />
      </div>
    </div>
  );
}
