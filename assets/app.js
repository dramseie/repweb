// --- jQuery must be first and global for many plugins (Trumbowyg, DT Buttons, etc.)
import $ from 'jquery';
window.$ = window.jQuery = $;
// Some UMD plugins look up bare `$` / `jQuery` on globalThis:
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

// Core + Bootstrap 5 styling (attaches to global jQuery)
import 'datatables.net-bs5';

// Extensions (use the -bs5 wrapper where available)
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5';   // HTML5 export buttons
import 'datatables.net-buttons/js/buttons.print';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-colreorder-bs5';
import 'datatables.net-fixedheader-bs5';
import 'datatables.net-scroller-bs5';
import 'datatables.net-searchbuilder-bs5';
import 'datatables.net-datetime';

// CSS for DT + extensions
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';
import 'datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css';
import 'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css';

/* ======================================================================
   React bootstraps
   ====================================================================== */
import React from 'react';
import { createRoot } from 'react-dom/client';
import MegaNavbar from './components/MegaNavbar';
import DataTablesReport from './components/DataTablesReport';
import PivotReport from './react/components/PivotReport';
import TilesDashboard from './components/TilesDashboard';
import WidgetsDashboard from './react/components/WidgetsDashboard';

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

// Mount DataTables report page
const elReport = document.getElementById('react-datatables-report');
if (elReport) createRoot(elReport).render(<DataTablesReport />);

// Mount the Pivot page when present
const elPivot = document.getElementById('react-pivot-report');
if (elPivot) {
  const reportId = parseInt(elPivot.getAttribute('data-report-id') || '0', 10);
  createRoot(elPivot).render(<PivotReport reportId={reportId} />);
}

// Mount the Tiles Dashboard when present
const elTiles = document.getElementById('react-tiles-dashboard');
if (elTiles) {
  createRoot(elTiles).render(<TilesDashboard showHeader />);
}

// NEW: Mount the Widgets Dashboard on the homepage container
const elWidgets = document.getElementById('react-widgets-dashboard');
if (elWidgets) {
  const apiBase = elWidgets.dataset.apiBase || '/api/widgets';
  createRoot(elWidgets).render(<WidgetsDashboard apiBase={apiBase} />);
}

/* ======================================================================
   EasyAdmin form editors: Trumbowyg (WYSIWYG) + CodeMirror v5
   ====================================================================== */

// Trumbowyg CSS can be static, but JS must load after jQuery is global
import 'trumbowyg/dist/ui/trumbowyg.min.css';
// Use the bundled SVG sprite as a URL so icons show correctly
import trumbowygIconsUrl from 'trumbowyg/dist/ui/icons.svg?url';

// Optional plugin CSS (only if you use the buttons)
import 'trumbowyg/dist/plugins/colors/ui/trumbowyg.colors.min.css';
// import 'trumbowyg/dist/plugins/emoji/ui/trumbowyg.emoji.css'; // enable if using emoji

// CodeMirror v5 (classic)
import 'codemirror/lib/codemirror.css';
import 'codemirror/theme/eclipse.css';
import CodeMirror from 'codemirror';
import 'codemirror/mode/sql/sql';
import 'codemirror/addon/edit/matchbrackets.js';
import 'codemirror/addon/edit/closebrackets.js';

/** Load Trumbowyg core + desired plugins exactly once (after jQuery is global) */
async function loadTrumbowygOnce() {
  if (window._twLoaded) return;
  await import('trumbowyg/dist/trumbowyg.min.js');
  await Promise.all([
    import('trumbowyg/dist/plugins/upload/trumbowyg.upload'),
    import('trumbowyg/dist/plugins/base64/trumbowyg.base64'),
    import('trumbowyg/dist/plugins/cleanpaste/trumbowyg.cleanpaste'),
    import('trumbowyg/dist/plugins/colors/trumbowyg.colors'),
    // import('trumbowyg/dist/plugins/emoji/trumbowyg.emoji'), // optional
  ]).catch(() => {});
  if ($.trumbowyg) $.trumbowyg.svgPath = trumbowygIconsUrl;
  window._twLoaded = true;
}

/** Initialize editors inside a context (document or container element) */
async function initEaEditors(context) {
  await loadTrumbowygOnce();
  const $ctx = context ? $(context) : $(document);

  // Trumbowyg WYSIWYG
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

  // CodeMirror (SQL / JSON etc.)
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

// Initial page load
document.addEventListener('DOMContentLoaded', () => {
  initEaEditors();
});

// Re-init when EasyAdmin dynamically adds form parts
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

// -------------------------------------------------------------------
// Exported helper so React components can init Trumbowyg on any node
// -------------------------------------------------------------------
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

/* ======================================================================
   React mount for Mail Template form (matches your Twig id)
   ====================================================================== */
import MailTemplateForm from './react/components/mail/MailTemplateForm';

const elMailForm = document.getElementById('react-mail-template-form');
if (elMailForm) {
  createRoot(elMailForm).render(<MailTemplateForm />);
}
