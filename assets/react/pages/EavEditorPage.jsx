// File: assets/react/pages/EavEditorPage.jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import EavHandsontable from '../components/EavHandsontable';

export default function mountEavEditor() {
  const el = document.getElementById('eav-editor-root');
  if (!el) return;
  const tenant = el.dataset.tenant;
  const entityType = el.dataset.entityType;
  createRoot(el).render(<EavHandsontable tenant={tenant} entityType={entityType} />);
}
