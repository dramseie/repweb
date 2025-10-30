// --- jQuery must be first and global for plugins (Trumbowyg, DT Buttons, daterangepicker)
import $ from 'jquery';
window.$ = window.jQuery = $;
globalThis.$ = $;
globalThis.jQuery = $;

// --- Moment + DateRangePicker (the 2-calendar/time preset picker)
import moment from 'moment';
window.moment = moment;
import 'daterangepicker';
import 'daterangepicker/daterangepicker.css';

// --- CSS & base UI
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import './styles/app.css';
import './styles/meganav-pro.css';

// Pivot UI CSS (drag/drop pivot controls)
import 'react-pivottable/pivottable.css';

// One Bootstrap JS import and expose for React after render
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
import 'datatables.net-datetime/css/dataTables.dateTime.scss';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';
import 'datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css';
import 'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css';

/* ======================================================================
   Register DateRangePicker condition for SearchBuilder
   ====================================================================== */
(function registerDateRangePickerSB() {
  const dt = $.fn.dataTable;
  if (!dt || !dt.ext || !dt.ext.searchBuilder) return;

  const SB = dt.ext.searchBuilder;
  if (SB._dateRangePickerRegistered) return; // avoid double-registration
  SB._dateRangePickerRegistered = true;

  const condition = {
    conditionName: 'in range…',
    init: (that, fn, preDefined = null) => {
      const $input = $('<input type="text" class="form-control dt-sb-daterange" autocomplete="off" style="min-width:240px;" aria-label="Date range">');
      const $s = $('<input type="hidden" class="dt-sb-start">');
      const $e = $('<input type="hidden" class="dt-sb-end">');
      const $wrap = $('<div/>').append($input, $s, $e);

      let start = null, end = null;
      if (preDefined?.value?.length === 2) {
        start = moment(preDefined.value[0]);
        end   = moment(preDefined.value[1]);
      }

      $input.daterangepicker({
        autoUpdateInput: !!(start && end),
        timePicker: true,            // supports DATETIME; harmless for DATE
        timePicker24Hour: true,
        locale: { format: 'YYYY-MM-DD HH:mm:ss', cancelLabel: 'Clear' },
        startDate: start || moment().startOf('day'),
        endDate:   end   || moment().endOf('day'),
        opens: 'left'
      });

      const write = (s, e, show = true) => {
        $s.val(s.format('YYYY-MM-DD HH:mm:ss'));
        $e.val(e.format('YYYY-MM-DD HH:mm:ss'));
        if (show) $input.val(`${$s.val()} — ${$e.val()}`);
        try { that.s.dt.draw(false); } catch {}
      };

      $input.on('apply.daterangepicker', (ev, picker) => write(picker.startDate, picker.endDate));
      $input.on('cancel.daterangepicker', () => { $input.val(''); $s.val(''); $e.val(''); try { that.s.dt.draw(false); } catch {} });

      if (start && end) write(start, end, true);
      return $wrap;
    },
    inputValue: (el) => {
      const $el = $(el);
      const v1 = $el.find('.dt-sb-start').val();
      const v2 = $el.find('.dt-sb-end').val();
      return (v1 && v2) ? [v1, v2] : [];
    },
    isInputValid: (el) => {
      const $el = $(el);
      const v1 = $el.find('.dt-sb-start').val();
      const v2 = $el.find('.dt-sb-end').val();
      return Boolean(v1 && v2);
    },
    search: (cellValue, comparison) => {
      if (!Array.isArray(comparison) || comparison.length !== 2) return true;
      const mcell = moment(cellValue);
      const s = moment(comparison[0]);
      const e = moment(comparison[1]);
      if (!mcell.isValid() || !s.isValid() || !e.isValid()) return true;
      return mcell.isBetween(s, e, undefined, '[]'); // inclusive
    },
    numInputs: 1,
    requiresInput: true
  };

  // Register for date-like types
  ['date', 'moment', 'luxon'].forEach((typeKey) => {
    SB.conditions[typeKey] = SB.conditions[typeKey] || {};
    SB.conditions[typeKey]['date_range_picker'] = condition;
  });
})();

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

// Tabbed widgets home
import WidgetsHomeTabs from './react/components/WidgetsHomeTabs.jsx';
// Legacy single-page widgets dashboard (compat)
import WidgetsDashboard from './react/components/WidgetsDashboard';

import ProgressPage from './react/pages/ProgressPage';
import DiscoveryManagerApp from './react/discovery/DiscoveryManagerApp.jsx';

// EAV Editor boot
import mountEavEditor from './react/pages/EavEditorPage';
import 'handsontable/dist/handsontable.full.min.css';

import { initWidgetZoom } from './react/lib/widgetZoom';
import './styles/widget-zoom.css';

// Service Catalog
import ServiceCatalogApp from './react/ServiceCatalogApp.jsx';

// POS apps
import PosApp from './react/components/PosApp.jsx';
import PosAgenda from './react/components/PosAgenda.jsx';

// Leaflet fixes (default marker icons)
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
window.App = Object.assign(window.App || {}, { ProgressPage, DiscoveryManagerApp });

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

/* ======================================================================
   Mounts (single DOMContentLoaded to avoid double-inits)
   ====================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  // Editors
  initEaEditors();

  // EAV
  mountEavEditor();

  // Widget zoom
  initWidgetZoom();
  window.addEventListener('widgets.action', (e) => {
    const a = e?.detail?.action;
    if (a === 'widgets:save' || a === 'widgets:reset' || a === 'widgets:add' || a === 'widgets:edit') {
      setTimeout(() => initWidgetZoom(), 0);
    }
  });

  // Global click bus for Widgets
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-widgets-action[data-action]');
    if (!btn) return;
    e.preventDefault();
    const action = btn.dataset.action;
    window.dispatchEvent(new CustomEvent('widgets.action', { detail: { action } }));
  });

  // Mega Navbar
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

  // Reports & dashboards
  const elReport = document.getElementById('react-datatables-report');
  if (elReport) createRoot(elReport).render(<DataTablesReport />);

  const elPivot = document.getElementById('react-pivot-report');
  if (elPivot) {
    const reportId = parseInt(elPivot.getAttribute('data-report-id') || '0', 10);
    createRoot(elPivot).render(<PivotReport reportId={reportId} />);
  }

  const elTiles = document.getElementById('react-tiles-dashboard');
  if (elTiles) createRoot(elTiles).render(<TilesDashboard showHeader />);

  // Tabbed widgets home (new) or legacy
  const elWidgetsHome = document.getElementById('widgets-home-root');
  if (elWidgetsHome) {
    const apiTabs   = elWidgetsHome.getAttribute('data-api-tabs')   || '/api/widgets/tabs';
    const apiWidgets= elWidgetsHome.getAttribute('data-api-widgets')|| '/api/widgets';
    const resetUrl  = elWidgetsHome.getAttribute('data-reset-url')  || '/api/widgets/tabs/reset-to-defaults';
    createRoot(elWidgetsHome).render(
      <WidgetsHomeTabs apiTabs={apiTabs} apiWidgets={apiWidgets} resetUrl={resetUrl} />
    );
  } else {
    const elWidgets = document.getElementById('react-widgets-dashboard');
    if (elWidgets) {
      const apiBase = elWidgets.dataset.apiBase || '/api/widgets';
      createRoot(elWidgets).render(<WidgetsDashboard apiBase={apiBase} />);
    }
  }

  // Mail template form
  const elMailForm = document.getElementById('react-mail-template-form');
  if (elMailForm) createRoot(elMailForm).render(<MailTemplateForm />);

  // JSON import builder
  const elJsonBuilder = document.getElementById('react-json-import-builder');
  if (elJsonBuilder) {
    createRoot(elJsonBuilder).render(
      <JsonImportQueryBuilder apiBase="/api/widgets" tableAlias="j" jsonColumn="ji_json" />
    );
  }

  // POS apps
  const posRoot = document.getElementById('pos-root');
  if (posRoot) createRoot(posRoot).render(<PosApp />);

  const agendaRoot = document.getElementById('pos-agenda-root');
  if (agendaRoot) createRoot(agendaRoot).render(<PosAgenda />);

  // Service Catalog (ID is server-rendered; safe to check now)
  const scRoot = document.getElementById('service-catalog-root');
  if (scRoot) createRoot(scRoot).render(<ServiceCatalogApp defaultTenant="cmdb" />);

  // Progress page
  const elProgress = document.getElementById('progress-root');
  if (elProgress) createRoot(elProgress).render(<ProgressPage />);

  const discoveryRoot = document.getElementById('discovery-root');
  if (discoveryRoot) {
    const apiBase = discoveryRoot.getAttribute('data-api-base') || '/api/discovery';
    createRoot(discoveryRoot).render(<DiscoveryManagerApp apiBase={apiBase} />);
  }

  // Rest API Explorer
  const restRoot = document.getElementById('rest-explorer-root');
  if (restRoot) createRoot(restRoot).render(<RestApiExplorer />);
});

/* Re-init editors on EA live events */
document.addEventListener('ea.collection.item-added', (e) => initEaEditors(e.target));
document.addEventListener('ea.form.partition-switch', (e) => {
  initEaEditors(e.target);
  $(e.target).find('textarea[data-editor="codemirror"]').each(function () {
    if (this._cm) setTimeout(() => this._cm.refresh(), 0);
  });
});
document.addEventListener('ea.form.controller.change', (e) => initEaEditors(e.target));

/* Exports */
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

// Mail + JSON builder + Rest Explorer components (imported after DOM ready check)
import MailTemplateForm from './react/components/mail/MailTemplateForm';
import JsonImportQueryBuilder from './react/components/JsonImportQueryBuilder';
import RestApiExplorer from './react/components/RestApiExplorer.jsx';

import MetaTab from './react/eav/MetaTab';
const el = document.getElementById('eav-meta-root');
if (el) createRoot(el).render(<MetaTab />);

import GrafanaBrowser from "./react/components/GrafanaBrowser";
const mount = document.getElementById("react-root");
if (mount) {
  const orgId = Number(mount.dataset.orgId || 1);
  const grafanaBase = mount.dataset.grafanaBase || "https://repweb.ramseier.com:3001";
  createRoot(mount).render(<GrafanaBrowser orgId={orgId} grafanaBase={grafanaBase} />);
}

/* ======================================================================
   Repweb Intro — modal & full-page mount (What’s / Who am I / Contact)
   ====================================================================== */
import "./styles/repweb-intro.css";
import RepwebIntro from "./react/components/RepwebIntro";

/** Mount the Repweb intro into the element with id="repweb-intro-root" */
function mountRepwebIntro(initialSlide = 0) {
  const elRoot = document.getElementById("repweb-intro-root");
  if (!elRoot) return;

  const mode = elRoot.dataset.mode || "modal";
  const root = createRoot(elRoot);

  const onClose = () => {
    root.unmount();
    elRoot.innerHTML = "";
  };

  // Pass initialSlide to open on any slide (0 = first, -1 = last / “Who am I”)
  root.render(<RepwebIntro mode={mode} onClose={onClose} initialSlide={initialSlide} />);
}

// Wire the three entry points on the login page
const btnWhat = document.getElementById("repweb-intro-btn"); // “What’s Repweb?”
const btnWho  = document.getElementById("repweb-who-btn");   // “Who am I”
if (btnWhat) {
  btnWhat.addEventListener("click", (e) => {
    e.preventDefault();
    mountRepwebIntro(0); // first slide
  });
}
if (btnWho) {
  btnWho.addEventListener("click", (e) => {
    e.preventDefault();
    mountRepwebIntro(-1); // last slide = “Who am I”
  });
}

// Auto-mount for full-page route; allow data-initial-slide override
const rootEl = document.getElementById("repweb-intro-root");
if (rootEl && !btnWhat && !btnWho) {
  const s = parseInt(rootEl.dataset.initialSlide || "0", 10);
  mountRepwebIntro(Number.isNaN(s) ? 0 : s);
}
