import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './modeler/App.jsx';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'reactflow/dist/style.css';
import './style.css';


createRoot(document.getElementById('cmdb-modeler-root')).render(<App />);
