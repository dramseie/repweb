import React from 'react';
import { createRoot } from 'react-dom/client';
import SqlStatementComposer from './components/SqlStatementComposer';

// Optional styling for the composer
import '../styles/sql-composer.css';

const mount = document.getElementById('sql-composer-root');
if (mount) {
  createRoot(mount).render(<SqlStatementComposer apiBase="/api/sqlc" />);
}
