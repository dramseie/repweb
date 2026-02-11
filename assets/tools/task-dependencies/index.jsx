import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './modeler/App.jsx';
import 'reactflow/dist/style.css';
import './style.css';

createRoot(document.getElementById('task-dependencies-root')).render(<App />);
