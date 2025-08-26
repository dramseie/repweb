
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './repweb.css';
import React from 'react';
import ReactDOM from 'react-dom/client';
import MegaNavbar from './widgets/MegaNavbar';

const el = document.getElementById('react-meganavbar');
if (el) {
  ReactDOM.createRoot(el).render(<MegaNavbar />);
}
