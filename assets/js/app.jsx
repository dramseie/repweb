
        import React from 'react';
        import { createRoot } from 'react-dom/client';
        import DashboardSelector from './components/DashboardSelector';
        const el = document.getElementById('react-root');
        if (el) { createRoot(el).render(<DashboardSelector />); }
        