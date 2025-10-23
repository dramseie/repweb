// assets/frw/app.js
import React from 'react';
import { createRoot } from 'react-dom/client';
import './styles/frw.css';
import FinancialWizard from './components/FinancialWizard.jsx';

const el = document.getElementById('frw-root');
const templateCode = el?.dataset?.templateCode;
createRoot(el).render(<FinancialWizard templateCode={templateCode} />);