// --- jQuery must be first and global for many plugins (Trumbowyg, DT Buttons, etc.)
import $ from 'jquery';
window.$ = window.jQuery = $;
globalThis.$ = $;
globalThis.jQuery = $;

// --- CSS & base UI
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import './styles/app.css';
import './styles/meganav-pro.css';

// Pivot UI CSS (needed for the drag/drop pivot controls)
import 'react-pivottable/pivottable.css';

// Use ONE Bootstrap JS import and expose it for React to use after render
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

/* ======================================================================
   File generators used by DT Buttons (expose BEFORE buttons import)
   ====================================================================== */
import JSZip from 'jszip';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
window.JSZip = JSZip;          // used by buttons.html5 (Excel/CSV)
pdfMake.vfs = pdfFonts.vfs;    // used by buttons.html5 (PDF)
window.pdfMake = pdfMake;

/* ======================================================================
   DataTables v2 — UMD side-effect imports (NO factory calls)
   Load core (with BS5 styling) -> extensions
   ====================================================================== */
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5';
import 'datatables.net-buttons/js/buttons.print';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-colreorder-bs5';
import 'datatables.net-fixedheader-bs5';
import 'datatables.net-scroller-bs5';
import 'datatables.net-searchbuilder-bs5';
import 'datatables.net-datetime';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';
import 'datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css';
import 'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css';

/* ======================================================================
   React bootstraps
   ====================================================================== */
import React from 'react';
import * as ReactDOMClient from 'react-dom/client';
const { createRoot } = ReactDOMClient;

import MegaNavbar from './components/MegaNavbar';
import DataTablesReport from './components/DataTablesReport';
import PivotReport from './react/components/PivotReport';
import TilesDashboard from './components/TilesDashboard';
import WidgetsDashboard from './react/components/WidgetsDashboard';
import ProgressPage from './react/pages/ProgressPage';

// EAV Editor boot
import mountEavEditor from './react/pages/EavEditorPage';
import 'handsontable/dist/handsontable.full.min.css';

document.addEventListener('DOMContentLoaded', () => {
  mountEavEditor();
});

// fix default marker icons (Leaflet)
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import icon2x from 'leaflet/dist/images/marker-icon-2x.png';
import icon   from 'leaflet/dist/images/marker-icon.png';
import shadow from 'leaflet/dist/images/marker-shadow.png';
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconRetinaUrl: icon2x, iconUrl: icon, shadowUrl: shadow });

// Expose for Twig mounts
window.React = React;
window.ReactDOMClient = ReactDOMClient;
window.App = Object.assign(window.App || {}, { ProgressPage });

// Mount the MegaNavbar and pass user info via data-* attributes on the container
const elNav = document.getElementById('react-meganavbar');
if (elNav) {
  const currentUser = {
    name: elNav.dataset.userName || '',
    email: elNav.dataset.userEmail || '',
    avatarUrl: elNav.dataset.userAvatar || '',
  };
  const csrfToken = elNav.dataset.csrfToken || '';

  createRoot(elNav).render(
    <MegaNavbar
      currentUser={currentUser}
      csrfToken={csrfToken}
      logoutPath="/logout"
    />
  );
}

// Global click bus for Widgets
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-widgets-action[data-action]');
  if (!btn) return;
  e.preventDefault();
  const action = btn.dataset.action;
  window.dispatchEvent(new CustomEvent('widgets.action', { detail: { action } }));
});

const elReport = document.getElementById('react-datatables-report');
if (elReport) createRoot(elReport).render(<DataTablesReport />);

const elPivot = document.getElementById('react-pivot-report');
if (elPivot) {
  const reportId = parseInt(elPivot.getAttribute('data-report-id') || '0', 10);
  createRoot(elPivot).render(<PivotReport reportId={reportId} />);
}

const elTiles = document.getElementById('react-tiles-dashboard');
if (elTiles) {
  createRoot(elTiles).render(<TilesDashboard showHeader />);
}

const elWidgets = document.getElementById('react-widgets-dashboard');
if (elWidgets) {
  const apiBase = elWidgets.dataset.apiBase || '/api/widgets';
  createRoot(elWidgets).render(<WidgetsDashboard apiBase={apiBase} />);
}

const elProgress = document.getElementById('progress-root');
if (elProgress) {
  createRoot(elProgress).render(<ProgressPage />);
}

/* ======================================================================
   EasyAdmin form editors: Trumbowyg (WYSIWYG) + CodeMirror v5
   ====================================================================== */
import 'trumbowyg/dist/ui/trumbowyg.min.css';
import trumbowygIconsUrl from 'trumbowyg/dist/ui/icons.svg?url';
import 'trumbowyg/dist/plugins/colors/ui/trumbowyg.colors.min.css';
// import 'trumbowyg/dist/plugins/emoji/ui/trumbowyg.emoji.css';

import 'codemirror/lib/codemirror.css';
import 'codemirror/theme/eclipse.css';
import CodeMirror from 'codemirror';
import 'codemirror/mode/sql/sql';
import 'codemirror/addon/edit/matchbrackets.js';
import 'codemirror/addon/edit/closebrackets.js';

async function loadTrumbowygOnce() {
  if (window._twLoaded) return;
  await import('trumbowyg/dist/trumbowyg.min.js');
  await Promise.all([
    import('trumbowyg/dist/plugins/upload/trumbowyg.upload'),
    import('trumbowyg/dist/plugins/base64/trumbowyg.base64'),
    import('trumbowyg/dist/plugins/cleanpaste/trumbowyg.cleanpaste'),
    import('trumbowyg/dist/plugins/colors/trumbowyg.colors'),
  ]).catch(() => {});
  if ($.trumbowyg) $.trumbowyg.svgPath = trumbowygIconsUrl;
  window._twLoaded = true;
}

async function initEaEditors(context) {
  await loadTrumbowygOnce();
  const $ctx = context ? $(context) : $(document);

  $ctx.find('textarea[data-editor="trumbowyg"]').each(function () {
    const $ta = $(this);
    if ($ta.data('tw-initialized')) return;

    $ta.trumbowyg({
      btns: [
        ['viewHTML'],
        ['undo', 'redo'],
        ['formatting'],
        ['strong', 'em', 'del'],
        ['superscript', 'subscript'],
        ['link'],
        ['unorderedList', 'orderedList'],
        ['horizontalRule'],
        ['removeformat'],
        ['fullscreen'],
      ],
      autogrow: true,
    });

    $ta.data('tw-initialized', true);
  });

  $ctx.find('textarea[data-editor="codemirror"]').each(function () {
    const ta = this;
    if (ta._cm) return;

    const mode = ta.getAttribute('data-mode') || 'text/x-sql';
    const cm = CodeMirror.fromTextArea(ta, {
      mode,
      theme: 'eclipse',
      lineNumbers: true,
      lineWrapping: true,
      matchBrackets: true,
      autoCloseBrackets: true,
      viewportMargin: Infinity,
    });
    ta._cm = cm;
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initEaEditors();
});

document.addEventListener('ea.collection.item-added', (e) => {
  initEaEditors(e.target);
});

document.addEventListener('ea.form.partition-switch', (e) => {
  initEaEditors(e.target);
  $(e.target).find('textarea[data-editor="codemirror"]').each(function () {
    if (this._cm) setTimeout(() => this._cm.refresh(), 0);
  });
});

document.addEventListener('ea.form.controller.change', (e) => {
  initEaEditors(e.target);
});

export async function initTrumbowygOn(el, options = {}) {
  await loadTrumbowygOnce();
  const $el = $(el);
  if ($el.data('tw-initialized')) return $el;
  $el.trumbowyg({
    btns: [
      ['viewHTML'],
      ['undo', 'redo'],
      ['formatting'],
      ['strong', 'em', 'del'],
      ['superscript', 'subscript'],
      ['link'],
      ['unorderedList', 'orderedList'],
      ['horizontalRule'],
      ['removeformat'],
      ['fullscreen'],
    ],
    autogrow: true,
    ...options,
  });
  $el.data('tw-initialized', true);
  return $el;
}

import MailTemplateForm from './react/components/mail/MailTemplateForm';
const elMailForm = document.getElementById('react-mail-template-form');
if (elMailForm) {
  createRoot(elMailForm).render(<MailTemplateForm />);
}

import JsonImportQueryBuilder from './react/components/JsonImportQueryBuilder';
const elJsonBuilder = document.getElementById('react-json-import-builder');
if (elJsonBuilder) {
  createRoot(elJsonBuilder).render(
    <JsonImportQueryBuilder
      apiBase="/api/widgets"
      tableAlias="j"
      jsonColumn="ji_json"
    />
  );
}
