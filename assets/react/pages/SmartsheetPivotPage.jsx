import React, { useCallback, useEffect, useRef, useState } from 'react';
import $ from 'jquery';
import Highcharts from 'highcharts';
import HighchartsReact from 'highcharts-react-official';
import HighchartsXRange from 'highcharts/modules/xrange';
import DataTablesReport from '../../components/DataTablesReport.jsx';
import TrumboField from '../components/common/TrumboField.jsx';

if (typeof Highcharts === 'object') {
  const initXRange = HighchartsXRange?.default || HighchartsXRange;
  if (typeof initXRange === 'function') {
    initXRange(Highcharts);
  }
  Highcharts.setOptions({ time: { useUTC: false } });
}

const formatValue = (value) => {
  if (value === null || value === undefined) {
    return '';
  }
  if (value instanceof Date) {
    return value.toISOString();
  }
  return String(value);
};

const formatYmd = (value) => {
  const date = value instanceof Date ? value : parseDateValue(value);
  if (!date) return '—';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const formatLongDate = (value) => formatYmd(value);

const resolveCellValue = (row, key) => {
  if (!row || !key) return null;
  return row[key] ?? null;
};

const formatDateTimeDisplay = (value) => {
  if (!value) return '—';
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return formatYmd(date);
};

const getRowField = (row, keys) => {
  if (!row || !Array.isArray(keys)) return null;
  for (const key of keys) {
    const value = resolveCellValue(row, key);
    if (value !== null && value !== undefined && value !== '') {
      return value;
    }
  }
  return null;
};

const cleanSiteName = (value) => {
  if (!value) return '';
  return String(value).replace(/^IKEAStore\s*-\s*/i, '').trim();
};

const formatSiteLabel = (site) => {
  if (!site) return '—';
  const cleanedName = cleanSiteName(site.siteName || '') || site.siteName || '';
  const siteId = site.siteId || '';
  if (cleanedName && siteId) {
    return `${cleanedName} (${siteId})`;
  }
  return cleanedName || siteId || '—';
};

const parseDateValue = (value) => {
  if (!value) return null;
  if (value instanceof Date) return value;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatShortDate = (value) => formatYmd(value);

const formatDateDisplay = (value) => formatYmd(value);

const formatDisplayValue = (v) => (v === null || v === undefined || v === '' ? '—' : String(v));

const formatActionLines = (value) => {
  if (value === null || value === undefined || value === '') return ['—'];
  const text = String(value);
  const withBreaks = text.replace(/(\d{2}\.\d{2}\.\d{2})/g, '\n$1');
  const lines = withBreaks
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.length > 0);
  return lines.length > 0 ? [lines[0]] : ['—'];
};

const formatMonthLabel = (value) => {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short' });
};

const buildMonthTicks = (min, max) => {
  const ticks = [];
  const start = new Date(min);
  start.setDate(1);
  start.setHours(0, 0, 0, 0);
  const end = new Date(max);
  end.setDate(1);
  end.setHours(0, 0, 0, 0);
  let cursor = new Date(start);
  while (cursor <= end) {
    const time = cursor.getTime();
    ticks.push({ time, label: formatMonthLabel(cursor) });
    cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);
  }
  return ticks;
};

const buildQuarterTicks = (min, max) => {
  const ticks = [];
  const start = new Date(min);
  const startQuarter = Math.floor(start.getMonth() / 3) * 3;
  start.setMonth(startQuarter, 1);
  start.setHours(0, 0, 0, 0);
  const end = new Date(max);
  end.setDate(1);
  end.setHours(0, 0, 0, 0);
  let cursor = new Date(start);
  while (cursor <= end) {
    const time = cursor.getTime();
    ticks.push({ time, label: formatMonthLabel(cursor) });
    cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 3, 1);
  }
  return ticks;
};

const COUNTRY_FLAG_MAP = {
  Australia: 'AU',
  Austria: 'AT',
  Belgium: 'BE',
  Bulgaria: 'BG',
  Canada: 'CA',
  Croatia: 'HR',
  Cyprus: 'CY',
  Czechia: 'CZ',
  'Czech Republic': 'CZ',
  Denmark: 'DK',
  Estonia: 'EE',
  Finland: 'FI',
  France: 'FR',
  Germany: 'DE',
  Greece: 'GR',
  Hungary: 'HU',
  Iceland: 'IS',
  India: 'IN',
  Ireland: 'IE',
  Italy: 'IT',
  Latvia: 'LV',
  Lithuania: 'LT',
  Luxembourg: 'LU',
  Malta: 'MT',
  Netherlands: 'NL',
  Norway: 'NO',
  Poland: 'PL',
  Portugal: 'PT',
  Romania: 'RO',
  Slovakia: 'SK',
  Slovenia: 'SI',
  Spain: 'ES',
  Sweden: 'SE',
  Switzerland: 'CH',
  'United Kingdom': 'GB',
  UK: 'GB',
  'United States': 'US',
  USA: 'US',
};

const countryFlagCode = (country) => {
  if (!country) {
    return '';
  }
  return COUNTRY_FLAG_MAP[country.trim()] || '';
};

const countryAnchorId = (country) => {
  if (!country) return '';
  return `country-${String(country)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')}`;
};

const CountryFlag = ({ country }) => {
  const code = countryFlagCode(country);
  if (!code) {
    return null;
  }
  const src = `https://flagcdn.com/24x18/${code.toLowerCase()}.png`;
  return <img src={src} alt="" width={24} height={18} style={{ marginRight: 8, verticalAlign: 'text-bottom' }} />;
};

const CountryAnchor = ({ country, className = '' }) => {
  if (!country) return null;
  const anchor = countryAnchorId(country);
  const classes = ['text-decoration-none', 'text-reset', className].filter(Boolean).join(' ');
  if (!anchor) {
    return (
      <span className={classes}>
        <CountryFlag country={country} />
        {country}
      </span>
    );
  }
  return (
    <a href={`#${anchor}`} className={classes}>
      <CountryFlag country={country} />
      {country}
    </a>
  );
};

const confidenceVariant = (value) => {
  if (value === null || value === undefined) {
    return 'secondary';
  }
  const normalized = String(value).toLowerCase();
  if (normalized.includes('high')) {
    return 'success';
  }
  if (normalized.includes('medium')) {
    return 'warning';
  }
  if (normalized.includes('low')) {
    return 'danger';
  }
  return 'secondary';
};

const confidenceColor = (value) => {
  const normalized = String(value || '').toLowerCase();
  if (normalized.includes('high')) return '#198754';
  if (normalized.includes('medium')) return '#ffc107';
  if (normalized.includes('low')) return '#dc3545';
  return '';
};

const SmartsheetPivotPage = () => {
  const [activeTab, setActiveTab] = useState('explorer');

  const [workspaces, setWorkspaces] = useState([]);
  const [workspaceError, setWorkspaceError] = useState(null);
  const [workspaceLoading, setWorkspaceLoading] = useState(false);

  const [sheets, setSheets] = useState([]);
  const [sheetsError, setSheetsError] = useState(null);
  const [sheetsLoading, setSheetsLoading] = useState(false);

  const [selectedWorkspace, setSelectedWorkspace] = useState('');
  const [selectedSheet, setSelectedSheet] = useState('');

  const [columns, setColumns] = useState([]);
  const [rows, setRows] = useState([]);
  const [pivotError, setPivotError] = useState(null);
  const [pivotLoading, setPivotLoading] = useState(false);
  const [lastUpdated, setLastUpdated] = useState(null);

  const [analyseTasks, setAnalyseTasks] = useState([]);
  const [analyseTaskError, setAnalyseTaskError] = useState(null);
  const [analyseTaskLoading, setAnalyseTaskLoading] = useState(false);

  const [taskA, setTaskA] = useState('');
  const [taskB, setTaskB] = useState('');
  const [fieldA, setFieldA] = useState('End_Date');
  const [fieldB, setFieldB] = useState('Start_Date');

  const [analyseColumns, setAnalyseColumns] = useState([]);
  const [analyseRows, setAnalyseRows] = useState([]);
  const [analyseError, setAnalyseError] = useState(null);
  const [analyseLoading, setAnalyseLoading] = useState(false);

  const [presentationAssessments, setPresentationAssessments] = useState({ meta: null, items: [] });
  const [presentationInstallations, setPresentationInstallations] = useState({ meta: null, items: [] });
  const [presentationPostDeployment, setPresentationPostDeployment] = useState({ meta: null, items: [] });
  const [presentationTimeline, setPresentationTimeline] = useState({ items: [] });
  const [timelineDrilldownCountry, setTimelineDrilldownCountry] = useState(null);
  const [presentationIssues, setPresentationIssues] = useState({ items: [], generalIssues: { ikea: [], hpe: [] } });
  const [presentationProgress, setPresentationProgress] = useState({ items: [] });
  const [presentationOverview, setPresentationOverview] = useState({ items: [] });
  const [overviewLoading, setOverviewLoading] = useState(false);
  const [overviewError, setOverviewError] = useState(null);
  const [presentationError, setPresentationError] = useState(null);
  const [presentationLoading, setPresentationLoading] = useState(false);
  const [presentationLoaded, setPresentationLoaded] = useState(false);
  const [presentationCountryFilter, setPresentationCountryFilter] = useState([]);
  const [presentationExporting, setPresentationExporting] = useState(false);
  const [presentationHtmlExporting, setPresentationHtmlExporting] = useState(false);
  const [presentationXlsxExporting, setPresentationXlsxExporting] = useState(false);
  const [presentationEditMode, setPresentationEditMode] = useState(false);
  const [presentationEdits, setPresentationEdits] = useState({});
  const [presentationSaving, setPresentationSaving] = useState(false);
  const [presentationSaveError, setPresentationSaveError] = useState(null);
  const [issueDrafts, setIssueDrafts] = useState({});
  const [issueSaving, setIssueSaving] = useState({});
  const [issueEditTargets, setIssueEditTargets] = useState({});
  const [slideshowOpen, setSlideshowOpen] = useState(false);
  const [slideshowIndex, setSlideshowIndex] = useState(0);
  const [highlightsContent, setHighlightsContent] = useState('');
  const [highlightsLoading, setHighlightsLoading] = useState(false);
  const [highlightsSaving, setHighlightsSaving] = useState(false);
  const [highlightsError, setHighlightsError] = useState(null);
  const [highlightsLoaded, setHighlightsLoaded] = useState(false);

  const [trendOverrides, setTrendOverrides] = useState({});
  const [trendDrafts, setTrendDrafts] = useState({});
  const [trendLoading, setTrendLoading] = useState(false);
  const [trendSaving, setTrendSaving] = useState(false);
  const [trendError, setTrendError] = useState(null);
  const [trendLoaded, setTrendLoaded] = useState(false);

  const [generalIssuesOverrides, setGeneralIssuesOverrides] = useState({});
  const [generalIssuesDrafts, setGeneralIssuesDrafts] = useState({});
  const [generalIssuesOverridesLoading, setGeneralIssuesOverridesLoading] = useState(false);
  const [generalIssuesOverridesError, setGeneralIssuesOverridesError] = useState(null);
  const [generalIssuesOverridesLoaded, setGeneralIssuesOverridesLoaded] = useState(false);

  const [overviewOverrides, setOverviewOverrides] = useState({});
  const [overviewDrafts, setOverviewDrafts] = useState({});
  const [overviewOverridesLoading, setOverviewOverridesLoading] = useState(false);
  const [overviewOverridesSaving, setOverviewOverridesSaving] = useState(false);
  const [overviewOverridesError, setOverviewOverridesError] = useState(null);
  const [overviewOverridesLoaded, setOverviewOverridesLoaded] = useState(false);

  const [plannedWeekCommentOverrides, setPlannedWeekCommentOverrides] = useState({});
  const [plannedWeekCommentDrafts, setPlannedWeekCommentDrafts] = useState({});
  const [plannedWeekCommentOverridesLoading, setPlannedWeekCommentOverridesLoading] = useState(false);
  const [plannedWeekCommentOverridesError, setPlannedWeekCommentOverridesError] = useState(null);
  const [plannedWeekCommentOverridesLoaded, setPlannedWeekCommentOverridesLoaded] = useState(false);

  const [statusData, setStatusData] = useState({ categories: [], items: [] });
  const [statusError, setStatusError] = useState(null);
  const [statusLoading, setStatusLoading] = useState(false);
  const [statusLoaded, setStatusLoaded] = useState(false);
  const [statusDrafts, setStatusDrafts] = useState({});
  const [statusCountryFilter, setStatusCountryFilter] = useState('');

  const [plannedWeekRows, setPlannedWeekRows] = useState([]);
  const [plannedWeekLoading, setPlannedWeekLoading] = useState(false);
  const [plannedWeekError, setPlannedWeekError] = useState(null);

  const [taskTrackerItems, setTaskTrackerItems] = useState([]);
  const [taskTrackerLoading, setTaskTrackerLoading] = useState(false);
  const [taskTrackerError, setTaskTrackerError] = useState(null);
  const [taskTrackerLoaded, setTaskTrackerLoaded] = useState(false);
  const [taskTrackerDraft, setTaskTrackerDraft] = useState({
    date: formatYmd(new Date()),
    category: '',
    description: '',
    responsible: '',
    tasks: [],
  });
  const [taskTrackerSaving, setTaskTrackerSaving] = useState(false);
  const [taskTrackerEditId, setTaskTrackerEditId] = useState(null);
  const [taskTrackerFilterCountry, setTaskTrackerFilterCountry] = useState('');
  const [taskTrackerFilterSiteName, setTaskTrackerFilterSiteName] = useState('');
  const [taskTrackerFilterTaskName, setTaskTrackerFilterTaskName] = useState('');
  const [taskTrackerSelectOpen, setTaskTrackerSelectOpen] = useState(false);
  const taskTrackerSelectRef = useRef(null);
  const [taskTrackerOptionItems, setTaskTrackerOptionItems] = useState([]);
  const [taskTrackerOptionsLoading, setTaskTrackerOptionsLoading] = useState(false);
  const [taskTrackerOptionsHasMore, setTaskTrackerOptionsHasMore] = useState(true);
  const [taskTrackerOptionsOffset, setTaskTrackerOptionsOffset] = useState(0);
  const taskTrackerOptionsOffsetRef = useRef(0);
  const taskTrackerOptionsLoadingRef = useRef(false);
  const [taskTrackerFilesById, setTaskTrackerFilesById] = useState({});
  const [taskTrackerFilesLoading, setTaskTrackerFilesLoading] = useState({});
  const [taskTrackerFilesUploading, setTaskTrackerFilesUploading] = useState({});
  const [taskTrackerFilesError, setTaskTrackerFilesError] = useState({});

  const [reportList, setReportList] = useState([]);
  const [reportLoading, setReportLoading] = useState(false);
  const [reportError, setReportError] = useState(null);
  const [selectedReportId, setSelectedReportId] = useState('');
  const [reportMeta, setReportMeta] = useState(null);
  const [reportMetaLoading, setReportMetaLoading] = useState(false);
  const [reportMetaError, setReportMetaError] = useState(null);

  const presentationDateLabel = formatLongDate(new Date());

  const workspacesAbortRef = useRef(null);
  const sheetsAbortRef = useRef(null);
  const pivotAbortRef = useRef(null);
  const datatableRef = useRef(null);
  const tableRef = useRef(null);
  const analyseDatatableRef = useRef(null);
  const analyseTableRef = useRef(null);
  const execOverviewRef = useRef(null);

  const destroyTable = useCallback(() => {
    if (datatableRef.current) {
      datatableRef.current.destroy();
      datatableRef.current = null;
    }
    if (tableRef.current) {
      const tbody = tableRef.current.querySelector('tbody');
      if (tbody) {
        tbody.innerHTML = '';
      }
    }
  }, []);

  const scrollToExecOverview = useCallback(() => {
    const target = execOverviewRef.current || document.getElementById('exec-overview');
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    const loadPlannedWeek = async () => {
      setPlannedWeekLoading(true);
      setPlannedWeekError(null);
      try {
        const res = await fetch('/api/smartsheet/presentation/planned-week');
        if (!res.ok) throw new Error(`Failed to load planned-week (HTTP ${res.status})`);
        const payload = await res.json();
        if (!cancelled) setPlannedWeekRows(Array.isArray(payload?.items) ? payload.items : []);
      } catch (err) {
        if (!cancelled) setPlannedWeekError(err.message || 'Failed to load planned-week');
      } finally {
        if (!cancelled) setPlannedWeekLoading(false);
      }
    };
    loadPlannedWeek();
    return () => { cancelled = true; };
  }, []);

  const fetchPresentation = useCallback(async () => {
    setPresentationLoading(true);
    setPresentationError(null);
    setOverviewLoading(true);
    setOverviewError(null);

    try {
      const [assessmentsResponse, installationsResponse, postDeploymentResponse, issuesResponse, progressResponse, overviewResponse, timelineResponse] = await Promise.all([
        fetch('/api/smartsheet/presentation/planned-assessments'),
        fetch('/api/smartsheet/presentation/planned-installations'),
        fetch('/api/smartsheet/presentation/post-deployment-signoff'),
        fetch('/api/smartsheet/presentation/issues'),
        fetch('/api/smartsheet/presentation/progress'),
        fetch('/api/smartsheet/presentation/overview'),
        fetch('/api/smartsheet/presentation/timeline'),
      ]);
      if (!assessmentsResponse.ok) {
        throw new Error(`Failed to load planned assessments (HTTP ${assessmentsResponse.status}).`);
      }
      if (!installationsResponse.ok) {
        throw new Error(`Failed to load planned installations (HTTP ${installationsResponse.status}).`);
      }
      if (!postDeploymentResponse.ok) {
        throw new Error(`Failed to load post-deployment sign-off (HTTP ${postDeploymentResponse.status}).`);
      }
      if (!issuesResponse.ok) {
        throw new Error(`Failed to load issue log (HTTP ${issuesResponse.status}).`);
      }
      if (!progressResponse.ok) {
        throw new Error(`Failed to load progress summary (HTTP ${progressResponse.status}).`);
      }
      if (!overviewResponse.ok) {
        throw new Error(`Failed to load programme overview (HTTP ${overviewResponse.status}).`);
      }
      if (!timelineResponse.ok) {
        throw new Error(`Failed to load timeline data (HTTP ${timelineResponse.status}).`);
      }
      const assessmentsPayload = await assessmentsResponse.json();
      const installationsPayload = await installationsResponse.json();
      const postDeploymentPayload = await postDeploymentResponse.json();
      const issuesPayload = await issuesResponse.json();
      const progressPayload = await progressResponse.json();
      const overviewPayload = await overviewResponse.json();
      const timelinePayload = await timelineResponse.json();
      setPresentationAssessments({
        meta: assessmentsPayload?.meta ?? null,
        items: Array.isArray(assessmentsPayload?.items) ? assessmentsPayload.items : [],
      });
      setPresentationInstallations({
        meta: installationsPayload?.meta ?? null,
        items: Array.isArray(installationsPayload?.items) ? installationsPayload.items : [],
      });
      setPresentationPostDeployment({
        meta: postDeploymentPayload?.meta ?? null,
        items: Array.isArray(postDeploymentPayload?.items) ? postDeploymentPayload.items : [],
      });
      setPresentationIssues({
        items: Array.isArray(issuesPayload?.items) ? issuesPayload.items : [],
        generalIssues: issuesPayload?.generalIssues ?? { ikea: [], hpe: [] },
      });
      setPresentationProgress({
        items: Array.isArray(progressPayload?.items) ? progressPayload.items : [],
      });
      setPresentationOverview({
        items: Array.isArray(overviewPayload?.items) ? overviewPayload.items : [],
      });
      setPresentationTimeline({
        items: Array.isArray(timelinePayload?.items) ? timelinePayload.items : [],
      });
      setPresentationEdits({});
      setPresentationLoaded(true);
    } catch (error) {
      setPresentationError(error.message || 'Unable to load presentation data.');
      setPresentationLoaded(true);
    } finally {
      setPresentationLoading(false);
      setOverviewLoading(false);
    }
  }, []);

  const fetchHighlights = useCallback(async () => {
    if (highlightsLoaded || highlightsLoading) return;
    setHighlightsLoading(true);
    setHighlightsError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/content?section=highlights');
      if (!response.ok) {
        throw new Error(`Failed to load highlights (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setHighlightsContent(payload?.content || '');
      setHighlightsLoaded(true);
    } catch (error) {
      setHighlightsError(error.message || 'Unable to load highlights.');
      setHighlightsLoaded(true);
    } finally {
      setHighlightsLoading(false);
    }
  }, [highlightsLoaded, highlightsLoading]);

  const fetchTrendOverrides = useCallback(async () => {
    if (trendLoaded || trendLoading) return;
    setTrendLoading(true);
    setTrendError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/content?section=trend_overrides');
      if (!response.ok) {
        throw new Error(`Failed to load trend overrides (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const rawContent = payload?.content || '';
      const parsed = rawContent ? JSON.parse(rawContent) : {};
      setTrendOverrides(parsed && typeof parsed === 'object' ? parsed : {});
      setTrendLoaded(true);
    } catch (error) {
      setTrendError(error.message || 'Unable to load trend overrides.');
      setTrendLoaded(true);
    } finally {
      setTrendLoading(false);
    }
  }, [trendLoaded, trendLoading]);

  const fetchOverviewOverrides = useCallback(async () => {
    if (overviewOverridesLoaded || overviewOverridesLoading) return;
    setOverviewOverridesLoading(true);
    setOverviewOverridesError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/content?section=overview_overrides');
      if (!response.ok) {
        throw new Error(`Failed to load overview overrides (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const rawContent = payload?.content || '';
      const parsed = rawContent ? JSON.parse(rawContent) : {};
      setOverviewOverrides(parsed && typeof parsed === 'object' ? parsed : {});
      setOverviewOverridesLoaded(true);
    } catch (error) {
      setOverviewOverridesError(error.message || 'Unable to load overview overrides.');
      setOverviewOverridesLoaded(true);
    } finally {
      setOverviewOverridesLoading(false);
    }
  }, [overviewOverridesLoaded, overviewOverridesLoading]);

  const fetchPlannedWeekCommentOverrides = useCallback(async () => {
    if (plannedWeekCommentOverridesLoaded || plannedWeekCommentOverridesLoading) return;
    setPlannedWeekCommentOverridesLoading(true);
    setPlannedWeekCommentOverridesError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/content?section=planned_week_comments');
      if (!response.ok) {
        throw new Error(`Failed to load planned week comment overrides (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const rawContent = payload?.content || '';
      const parsed = rawContent ? JSON.parse(rawContent) : {};
      setPlannedWeekCommentOverrides(parsed && typeof parsed === 'object' ? parsed : {});
      setPlannedWeekCommentOverridesLoaded(true);
    } catch (error) {
      setPlannedWeekCommentOverridesError(error.message || 'Unable to load planned week comment overrides.');
      setPlannedWeekCommentOverridesLoaded(true);
    } finally {
      setPlannedWeekCommentOverridesLoading(false);
    }
  }, [plannedWeekCommentOverridesLoaded, plannedWeekCommentOverridesLoading]);

  const fetchGeneralIssuesOverrides = useCallback(async () => {
    if (generalIssuesOverridesLoaded || generalIssuesOverridesLoading) return;
    setGeneralIssuesOverridesLoading(true);
    setGeneralIssuesOverridesError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/content?section=general_issues_overrides');
      if (!response.ok) {
        throw new Error(`Failed to load general issues overrides (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const rawContent = payload?.content || '';
      const parsed = rawContent ? JSON.parse(rawContent) : {};
      setGeneralIssuesOverrides(parsed && typeof parsed === 'object' ? parsed : {});
      setGeneralIssuesOverridesLoaded(true);
    } catch (error) {
      setGeneralIssuesOverridesError(error.message || 'Unable to load general issues overrides.');
      setGeneralIssuesOverridesLoaded(true);
    } finally {
      setGeneralIssuesOverridesLoading(false);
    }
  }, [generalIssuesOverridesLoaded, generalIssuesOverridesLoading]);

  const fetchStatus = useCallback(async () => {
    setStatusLoading(true);
    setStatusError(null);

    try {
      const response = await fetch('/api/smartsheet/presentation/status');
      if (!response.ok) {
        throw new Error(`Failed to load status data (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setStatusData({
        categories: Array.isArray(payload?.categories) ? payload.categories : [],
        items: Array.isArray(payload?.items) ? payload.items : [],
      });
      setStatusLoaded(true);
    } catch (error) {
      setStatusError(error.message || 'Unable to load status data.');
    } finally {
      setStatusLoading(false);
    }
  }, []);

  const fetchReports = useCallback(async () => {
    setReportLoading(true);
    setReportError(null);
    try {
      const response = await fetch('/api/reports/tenant/IKEA');
      if (!response.ok) {
        throw new Error(`Failed to load reports (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const items = Array.isArray(payload?.reports) ? payload.reports : [];
      setReportList(items);
      if (!selectedReportId && items.length > 0) {
        setSelectedReportId(String(items[0].repid));
      }
    } catch (error) {
      setReportError(error.message || 'Unable to load reports.');
    } finally {
      setReportLoading(false);
    }
  }, [selectedReportId]);

  const fetchTaskTracker = useCallback(async () => {
    setTaskTrackerLoading(true);
    setTaskTrackerError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/task-tracker');
      if (!response.ok) {
        throw new Error(`Failed to load task tracker (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setTaskTrackerItems(Array.isArray(payload?.items) ? payload.items : []);
      setTaskTrackerLoaded(true);
    } catch (error) {
      setTaskTrackerError(error.message || 'Unable to load task tracker.');
    } finally {
      setTaskTrackerLoading(false);
    }
  }, []);

  const fetchReportMeta = useCallback(async (repid) => {
    if (!repid) {
      setReportMeta(null);
      return;
    }
    setReportMetaLoading(true);
    setReportMetaError(null);
    try {
      const response = await fetch(`/api/report/${encodeURIComponent(repid)}/meta`);
      if (!response.ok) {
        throw new Error(`Failed to load report meta (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setReportMeta(payload?.report ?? null);
    } catch (error) {
      setReportMetaError(error.message || 'Unable to load report details.');
      setReportMeta(null);
    } finally {
      setReportMetaLoading(false);
    }
  }, []);

  const destroyAnalyseTable = useCallback(() => {
    if (analyseDatatableRef.current) {
      analyseDatatableRef.current.destroy();
      analyseDatatableRef.current = null;
    }
    if (analyseTableRef.current) {
      const tbody = analyseTableRef.current.querySelector('tbody');
      if (tbody) {
        tbody.innerHTML = '';
      }
    }
  }, []);

  const hydrateTable = useCallback((cols, dataRows) => {
    if (!tableRef.current) {
      return;
    }

    destroyTable();

    if (!cols.length) {
      return;
    }

    const dataset = dataRows.map((row) => cols.map((col) => formatValue(resolveCellValue(row, col.key))));

    datatableRef.current = $(tableRef.current).DataTable({
      dom:
        "<'row g-2 align-items-center'<'col-md-7 dt-toolbar-left d-flex align-items-center'B><'col-md-5 dt-toolbar-right'f>>" +
        "<'row'<'col-12'tr>>" +
        "<'row'<'col-md-5'i><'col-md-7'p>>",
      data: dataset,
      columns: cols.map((col) => ({ title: col.label })),
      buttons: [
        { extend: 'colvis', text: '<i class="fas fa-columns me-1"></i> Columns', className: 'btn btn-sm btn-outline-secondary' },
        { extend: 'copyHtml5', text: '<i class="fas fa-copy me-1"></i> Copy', className: 'btn btn-sm btn-outline-secondary' },
        { extend: 'excelHtml5', text: '<i class="fas fa-file-excel me-1"></i> XLSX', className: 'btn btn-sm btn-success' },
        { extend: 'csvHtml5', text: '<i class="fas fa-file-csv me-1"></i> CSV', className: 'btn btn-sm btn-outline-primary' },
        { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf me-1"></i> PDF', className: 'btn btn-sm btn-outline-danger' },
        { extend: 'print', text: '<i class="fas fa-print me-1"></i> Print', className: 'btn btn-sm btn-outline-secondary' },
        { extend: 'searchBuilder', text: '<i class="fas fa-filter me-1"></i> Filter', className: 'btn btn-sm btn-outline-primary' },
      ],
      pageLength: 50,
      lengthMenu: [25, 50, 100, 250],
      fixedHeader: true,
      colReorder: true,
      responsive: false,
      scrollY: '60vh',
      scrollX: true,
      scrollCollapse: true,
      deferRender: true,
      scroller: true,
      stateSave: true,
      searchBuilder: true,
      language: { searchBuilder: { button: '<i class="fas fa-filter me-1"></i> Filter' } },
      order: [],
    });
  }, [destroyTable]);

  const hydrateAnalyseTable = useCallback((cols, dataRows) => {
    if (!analyseTableRef.current) {
      return;
    }

    destroyAnalyseTable();

    if (!cols.length) {
      return;
    }

    const dataset = dataRows.map((row) => cols.map((col) => formatValue(resolveCellValue(row, col.key))));

    analyseDatatableRef.current = $(analyseTableRef.current).DataTable({
      dom:
        "<'row g-2 align-items-center'<'col-md-7 dt-toolbar-left d-flex align-items-center'B><'col-md-5 dt-toolbar-right'f>>" +
        "<'row'<'col-12'tr>>" +
        "<'row'<'col-md-5'i><'col-md-7'p>>",
      data: dataset,
      columns: cols.map((col) => ({ title: col.label })),
      buttons: [
        { extend: 'colvis', text: '<i class="fas fa-columns me-1"></i> Columns', className: 'btn btn-sm btn-outline-secondary' },
        { extend: 'copyHtml5', text: '<i class="fas fa-copy me-1"></i> Copy', className: 'btn btn-sm btn-outline-secondary' },
        { extend: 'excelHtml5', text: '<i class="fas fa-file-excel me-1"></i> XLSX', className: 'btn btn-sm btn-success' },
        { extend: 'csvHtml5', text: '<i class="fas fa-file-csv me-1"></i> CSV', className: 'btn btn-sm btn-outline-primary' },
        { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf me-1"></i> PDF', className: 'btn btn-sm btn-outline-danger' },
        { extend: 'print', text: '<i class="fas fa-print me-1"></i> Print', className: 'btn btn-sm btn-outline-secondary' },
      ],
      pageLength: 50,
      lengthMenu: [25, 50, 100, 250],
      fixedHeader: true,
      colReorder: true,
      responsive: false,
      scrollY: '60vh',
      scrollX: true,
      scrollCollapse: true,
      deferRender: true,
      scroller: true,
      stateSave: true,
      order: [],
    });
  }, [destroyAnalyseTable]);

  const fetchWorkspaces = useCallback(async () => {
    workspacesAbortRef.current?.abort();
    const controller = new AbortController();
    workspacesAbortRef.current = controller;

    setWorkspaceLoading(true);
    setWorkspaceError(null);

    try {
      const response = await fetch('/api/smartsheet/pivot/workspaces', { signal: controller.signal });
      if (!response.ok) {
        throw new Error(`Failed to load workspaces (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setWorkspaces(Array.isArray(payload?.items) ? payload.items : []);
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }
      setWorkspaceError(error.message || 'Unable to load workspaces.');
    } finally {
      if (workspacesAbortRef.current === controller) {
        workspacesAbortRef.current = null;
      }
      setWorkspaceLoading(false);
    }
  }, []);

  const fetchSheets = useCallback(async (workspaceId) => {
    sheetsAbortRef.current?.abort();
    const controller = new AbortController();
    sheetsAbortRef.current = controller;

    setSheets([]);
    setSelectedSheet('');
    setColumns([]);
    setRows([]);
    destroyTable();
    setSheetsLoading(true);
    setSheetsError(null);

    if (!workspaceId) {
      setSheetsLoading(false);
      return;
    }

    try {
      const response = await fetch(`/api/smartsheet/pivot/workspaces/${encodeURIComponent(workspaceId)}/sheets`, { signal: controller.signal });
      if (!response.ok) {
        throw new Error(`Failed to load sheets (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setSheets(Array.isArray(payload?.items) ? payload.items : []);
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }
      setSheetsError(error.message || 'Unable to load sheets.');
    } finally {
      if (sheetsAbortRef.current === controller) {
        sheetsAbortRef.current = null;
      }
      setSheetsLoading(false);
    }
  }, [destroyTable]);

  const fetchPivot = useCallback(async (sheetId) => {
    pivotAbortRef.current?.abort();
    const controller = new AbortController();
    pivotAbortRef.current = controller;

    setPivotLoading(true);
    setPivotError(null);
    setColumns([]);
    setRows([]);
    destroyTable();

    if (!sheetId) {
      setPivotLoading(false);
      return;
    }

    try {
      const response = await fetch(`/api/smartsheet/pivot/sheets/${encodeURIComponent(sheetId)}/data`, { signal: controller.signal });
      if (!response.ok) {
        throw new Error(`Failed to load sheet data (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const cols = Array.isArray(payload?.columns) ? payload.columns : [];
      const dataRows = Array.isArray(payload?.items) ? payload.items : [];
      setColumns(cols);
      setRows(dataRows);
      setLastUpdated(new Date());
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }
      setPivotError(error.message || 'Unable to load Smartsheet data.');
    } finally {
      if (pivotAbortRef.current === controller) {
        pivotAbortRef.current = null;
      }
      setPivotLoading(false);
    }
  }, [destroyTable, hydrateTable]);

  useEffect(() => {
    fetchWorkspaces();

    const controller = new AbortController();
    const fetchTasks = async () => {
      setAnalyseTaskLoading(true);
      setAnalyseTaskError(null);
      try {
        const response = await fetch('/api/smartsheet/analyse/tasks', { signal: controller.signal });
        if (!response.ok) {
          throw new Error(`Failed to load tasks (HTTP ${response.status}).`);
        }
        const payload = await response.json();
        setAnalyseTasks(Array.isArray(payload?.items) ? payload.items : []);
      } catch (error) {
        if (error.name === 'AbortError') {
          return;
        }
        setAnalyseTaskError(error.message || 'Unable to load task list.');
      } finally {
        setAnalyseTaskLoading(false);
      }
    };

    fetchTasks();

    return () => {
      workspacesAbortRef.current?.abort();
      sheetsAbortRef.current?.abort();
      pivotAbortRef.current?.abort();
      controller.abort();
      destroyTable();
      destroyAnalyseTable();
    };
  }, [destroyAnalyseTable, destroyTable, fetchWorkspaces]);

  useEffect(() => {
    fetchSheets(selectedWorkspace);
  }, [fetchSheets, selectedWorkspace]);

  useEffect(() => {
    fetchPivot(selectedSheet);
  }, [fetchPivot, selectedSheet]);

  useEffect(() => {
    if (!columns.length) {
      destroyTable();
      return;
    }

    hydrateTable(columns, rows);
  }, [columns, rows, destroyTable, hydrateTable]);

  useEffect(() => {
    if (!analyseColumns.length) {
      destroyAnalyseTable();
      return;
    }

    hydrateAnalyseTable(analyseColumns, analyseRows);
  }, [analyseColumns, analyseRows, destroyAnalyseTable, hydrateAnalyseTable]);

  const onWorkspaceChange = (event) => {
    setSelectedWorkspace(event.target.value);
    setSelectedSheet('');
  };

  const onSheetChange = (event) => {
    setSelectedSheet(event.target.value);
  };

  const onRefresh = () => {
    if (selectedSheet) {
      fetchPivot(selectedSheet);
    }
  };

  const onRunAnalysis = async () => {
    if (!taskA || !taskB || !fieldA || !fieldB) {
      return;
    }

    setAnalyseLoading(true);
    setAnalyseError(null);
    setAnalyseColumns([]);
    setAnalyseRows([]);

    try {
      const params = new URLSearchParams({
        taskA,
        taskB,
        fieldA,
        fieldB,
      });
      const response = await fetch(`/api/smartsheet/analyse/durations?${params.toString()}`);
      if (!response.ok) {
        throw new Error(`Failed to run analysis (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setAnalyseColumns(Array.isArray(payload?.columns) ? payload.columns : []);
      setAnalyseRows(Array.isArray(payload?.items) ? payload.items : []);
    } catch (error) {
      setAnalyseError(error.message || 'Unable to run analysis.');
    } finally {
      setAnalyseLoading(false);
    }
  };

  const currentWorkspace = workspaces.find((item) => String(item.id) === String(selectedWorkspace));
  const currentSheet = sheets.find((item) => String(item.id) === String(selectedSheet));

  const taskOptions = analyseTasks.map((taskName) => ({
    value: taskName,
    label: taskName,
  }));

  const taskTrackerOptions = React.useMemo(() => {
    const map = new Map();
    const pushOption = (option) => {
      if (!option) return;
      const key = String(option.taskId || option.label || option.value || '');
      if (!key) return;
      if (!map.has(key)) {
        map.set(key, { ...option, value: key, label: option.label || option.value || key });
      }
    };
    taskTrackerOptionItems.forEach((item) => {
      const country = String(item?.country || '').trim();
      const siteName = String(item?.siteName || '').trim();
      const siteId = String(item?.siteId || '').trim();
      const taskName = String(item?.taskName || '').trim();
      const taskId = item?.taskId ? String(item.taskId) : null;
      const labelParts = [country, siteName || siteId, taskName].filter(Boolean);
      const label = labelParts.join(' / ');
      if (!label) return;
      pushOption({
        taskId,
        label,
        country,
        siteName: siteName || siteId,
        taskName,
      });
    });

    normalizeTaskTrackerTasks(taskTrackerDraft.tasks).forEach((task) =>
      pushOption({
        taskId: task.taskId,
        label: task.label,
        country: task.country ?? null,
        siteName: task.siteName ?? null,
        taskName: task.taskName ?? null,
      })
    );

    return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label));
  }, [taskTrackerOptionItems, taskTrackerDraft.tasks]);

  const taskTrackerFilteredOptions = taskTrackerOptions;

  const taskTrackerSelectedKeys = React.useMemo(() => {
    const keys = new Set();
    normalizeTaskTrackerTasks(taskTrackerDraft.tasks).forEach((task) => {
      const key = getTaskTrackerTaskKey(task);
      if (key) keys.add(key);
    });
    return keys;
  }, [taskTrackerDraft.tasks]);

  const taskTrackerTaskIdLookup = React.useMemo(() => {
    const map = new Map();
    taskTrackerOptions.forEach((option) => {
      if (option.label && option.taskId) {
        map.set(String(option.label), String(option.taskId));
      }
      if (option.value && option.taskId) {
        map.set(String(option.value), String(option.taskId));
      }
    });
    return map;
  }, [taskTrackerOptions]);

  const fetchTaskTrackerOptions = useCallback(async (mode = 'append') => {
    if (taskTrackerOptionsLoadingRef.current) return;
    taskTrackerOptionsLoadingRef.current = true;
    setTaskTrackerOptionsLoading(true);
    const nextOffset = mode === 'append' ? taskTrackerOptionsOffsetRef.current : 0;
    try {
      const params = new URLSearchParams({
        offset: String(nextOffset),
        limit: '200',
      });
      if (taskTrackerFilterCountry.trim()) params.set('country', taskTrackerFilterCountry.trim());
      if (taskTrackerFilterSiteName.trim()) params.set('site', taskTrackerFilterSiteName.trim());
      if (taskTrackerFilterTaskName.trim()) params.set('task', taskTrackerFilterTaskName.trim());
      const response = await fetch(`/api/smartsheet/presentation/task-tracker/options?${params.toString()}`);
      if (!response.ok) {
        throw new Error(`Failed to load task tracker options (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      const items = Array.isArray(payload?.items) ? payload.items : [];
      setTaskTrackerOptionItems((prev) => (mode === 'append' ? [...prev, ...items] : items));
      const updatedOffset = nextOffset + items.length;
      taskTrackerOptionsOffsetRef.current = updatedOffset;
      setTaskTrackerOptionsOffset(updatedOffset);
      setTaskTrackerOptionsHasMore(Boolean(payload?.hasMore) && items.length > 0);
    } catch (error) {
      setTaskTrackerOptionsHasMore(false);
    } finally {
      taskTrackerOptionsLoadingRef.current = false;
      setTaskTrackerOptionsLoading(false);
    }
  }, [taskTrackerFilterCountry, taskTrackerFilterSiteName, taskTrackerFilterTaskName]);

  const fetchTaskTrackerFiles = useCallback(async (entryId) => {
    if (!entryId) return;
    setTaskTrackerFilesLoading((prev) => ({ ...prev, [entryId]: true }));
    setTaskTrackerFilesError((prev) => ({ ...prev, [entryId]: null }));
    try {
      const response = await fetch(`/api/smartsheet/presentation/task-tracker/${entryId}/files`);
      if (!response.ok) {
        throw new Error(`Failed to load files (HTTP ${response.status}).`);
      }
      const payload = await response.json();
      setTaskTrackerFilesById((prev) => ({
        ...prev,
        [entryId]: Array.isArray(payload?.items) ? payload.items : [],
      }));
    } catch (error) {
      setTaskTrackerFilesError((prev) => ({
        ...prev,
        [entryId]: error.message || 'Failed to load files.',
      }));
    } finally {
      setTaskTrackerFilesLoading((prev) => ({ ...prev, [entryId]: false }));
    }
  }, []);

  const uploadTaskTrackerFiles = useCallback(async (entryId, files) => {
    if (!entryId || !files || files.length === 0) return;
    setTaskTrackerFilesUploading((prev) => ({ ...prev, [entryId]: true }));
    setTaskTrackerFilesError((prev) => ({ ...prev, [entryId]: null }));
    try {
      const formData = new FormData();
      Array.from(files).forEach((file) => {
        formData.append('files[]', file);
      });
      const response = await fetch(`/api/smartsheet/presentation/task-tracker/${entryId}/files`, {
        method: 'POST',
        body: formData,
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `Failed to upload files (HTTP ${response.status}).`);
      }
      await fetchTaskTrackerFiles(entryId);
    } catch (error) {
      setTaskTrackerFilesError((prev) => ({
        ...prev,
        [entryId]: error.message || 'Failed to upload files.',
      }));
    } finally {
      setTaskTrackerFilesUploading((prev) => ({ ...prev, [entryId]: false }));
    }
  }, [fetchTaskTrackerFiles]);

  useEffect(() => {
    if (!taskTrackerSelectOpen) return undefined;
    const handle = setTimeout(() => {
      taskTrackerOptionsOffsetRef.current = 0;
      setTaskTrackerOptionsOffset(0);
      setTaskTrackerOptionsHasMore(true);
      setTaskTrackerOptionItems([]);
      fetchTaskTrackerOptions('replace');
    }, 250);
    return () => clearTimeout(handle);
  }, [taskTrackerFilterCountry, taskTrackerFilterSiteName, taskTrackerFilterTaskName, fetchTaskTrackerOptions]);

  const taskTrackerTopCategories = React.useMemo(() => {
    const counts = new Map();
    taskTrackerItems.forEach((entry) => {
      const value = String(entry?.category || '').trim();
      if (!value) return;
      counts.set(value, (counts.get(value) || 0) + 1);
    });
    return Array.from(counts.entries())
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([value]) => value);
  }, [taskTrackerItems]);

  const taskTrackerTopResponsibles = React.useMemo(() => {
    const counts = new Map();
    taskTrackerItems.forEach((entry) => {
      const value = String(entry?.responsible || '').trim();
      if (!value) return;
      counts.set(value, (counts.get(value) || 0) + 1);
    });
    return Array.from(counts.entries())
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([value]) => value);
  }, [taskTrackerItems]);

  useEffect(() => {
    if (!taskTrackerSelectOpen) return;
    const handleClick = (event) => {
      if (!taskTrackerSelectRef.current) return;
      if (!taskTrackerSelectRef.current.contains(event.target)) {
        setTaskTrackerSelectOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, [taskTrackerSelectOpen]);

  const fieldOptions = [
    { value: 'Start_Date', label: 'Start Date' },
    { value: 'End_Date', label: 'End Date' },
  ];

  const assessmentItems = Array.isArray(presentationAssessments.items) ? presentationAssessments.items : [];
  const installationItems = Array.isArray(presentationInstallations.items) ? presentationInstallations.items : [];
  const postDeploymentItems = Array.isArray(presentationPostDeployment.items) ? presentationPostDeployment.items : [];
  const issueItems = Array.isArray(presentationIssues.items) ? presentationIssues.items : [];
  const assessmentMeta = presentationAssessments.meta ?? null;
  const installationMeta = presentationInstallations.meta ?? null;
  const postDeploymentMeta = presentationPostDeployment.meta ?? null;
  const presentationMeta = assessmentMeta ?? installationMeta ?? postDeploymentMeta;
  const overviewItems = Array.isArray(presentationOverview.items) ? presentationOverview.items : [];
  const progressItems = Array.isArray(presentationProgress.items) ? presentationProgress.items : [];

  const mergeCountryItems = (primary, secondary, tertiary, issues) => {
    const map = new Map();
    primary.forEach((block) => {
      map.set(block.country, { country: block.country, assessments: block, installations: null, postDeployment: null, issues: null });
    });
    secondary.forEach((block) => {
      const existing = map.get(block.country);
      if (existing) {
        existing.installations = block;
      } else {
        map.set(block.country, { country: block.country, assessments: null, installations: block, postDeployment: null, issues: null });
      }
    });
    tertiary.forEach((block) => {
      const existing = map.get(block.country);
      if (existing) {
        existing.postDeployment = block;
      } else {
        map.set(block.country, { country: block.country, assessments: null, installations: null, postDeployment: block, issues: null });
      }
    });
    issues.forEach((block) => {
      const existing = map.get(block.country);
      if (existing) {
        existing.issues = block;
      } else {
        map.set(block.country, { country: block.country, assessments: null, installations: null, postDeployment: null, issues: block });
      }
    });
    return Array.from(map.values()).sort((a, b) => a.country.localeCompare(b.country));
  };

  const presentationItems = mergeCountryItems(assessmentItems, installationItems, postDeploymentItems, issueItems);
  const presentationCountries = Array.from(new Set(presentationItems.map((item) => item.country))).sort((a, b) => a.localeCompare(b));
  const isSpecialFilter = (value) => value.startsWith('__');
  const selectedCountryValues = presentationCountryFilter.filter((value) => !isSpecialFilter(value));
  const selectedSpecialValues = presentationCountryFilter.filter(isSpecialFilter);
  
  const filterByCountry = (items) => {
    if (!selectedCountryValues.length) {
      return items;
    }
    return items.filter((item) => selectedCountryValues.includes(item.country));
  };
  
  const filteredAssessmentItems = filterByCountry(assessmentItems);
  const filteredInstallationItems = filterByCountry(installationItems);
  const filteredPostDeploymentItems = filterByCountry(postDeploymentItems);
  const filteredIssueItems = filterByCountry(issueItems);
  const filteredOverviewItems = filterByCountry(overviewItems);
  const filteredProgressItems = filterByCountry(progressItems);
  const timelineItems = filterByCountry(Array.isArray(presentationTimeline.items) ? presentationTimeline.items : []);

  const overviewByCountry = React.useMemo(() => {
    const map = new Map();
    filteredOverviewItems.forEach((row) => {
      if (row?.country) {
        map.set(row.country, row);
      }
    });
    return map;
  }, [filteredOverviewItems]);

  const timelineDomain = React.useMemo(() => {
    let min = null;
    let max = null;
    timelineItems.forEach((item) => {
      const start = parseDateValue(item.startDate);
      const end = parseDateValue(item.endDate);
      if (!start || !end) {
        return;
      }
      const startTime = start.getTime();
      const endTime = end.getTime();
      min = min === null ? startTime : Math.min(min, startTime);
      max = max === null ? endTime : Math.max(max, endTime);
    });
    if (min === null || max === null) {
      return null;
    }
    if (min === max) {
      max = min + 24 * 60 * 60 * 1000;
    }
    const now = Date.now();
    return {
      min,
      max,
      span: max - min,
      now: now >= min && now <= max ? now : null,
      ticks: buildMonthTicks(min, max),
      quarterTicks: buildQuarterTicks(min, max),
    };
  }, [timelineItems]);

  const timelineSiteMap = React.useMemo(() => {
    const map = new Map();
    timelineItems.forEach((item) => {
      const country = item.country || 'Unspecified';
      map.set(country, Array.isArray(item.sites) ? item.sites : []);
    });
    return map;
  }, [timelineItems]);

  const filteredPresentationItems = selectedCountryValues.length
    ? presentationItems.filter((item) => selectedCountryValues.includes(item.country))
    : presentationItems;

  const statusCategories = Array.isArray(statusData.categories) ? statusData.categories : [];
  const statusItems = Array.isArray(statusData.items) ? statusData.items : [];
  const statusCountries = Array.from(new Set(statusItems.map((item) => item.country))).sort((a, b) => a.localeCompare(b));

  const statusKey = (country, siteId) => `${country}||${siteId}`;

  const buildSiteOptions = (countryBlock) => {
    const map = new Map();
    const addEntries = (entries) => {
      (entries || []).forEach((entry) => {
        const siteId = entry.siteId ? String(entry.siteId) : '';
        const siteName = entry.siteName ? String(entry.siteName) : '';
        if (!siteId && !siteName) return;
        const key = siteId || siteName;
        if (!map.has(key)) {
          map.set(key, { siteId, siteName });
        }
      });
    };
    addEntries(countryBlock.assessments?.current);
    addEntries(countryBlock.assessments?.next);
    addEntries(countryBlock.installations?.current);
    addEntries(countryBlock.installations?.next);
    addEntries(countryBlock.postDeployment?.current);
    addEntries(countryBlock.postDeployment?.next);
    return Array.from(map.values()).sort((a, b) => (a.siteName || a.siteId).localeCompare(b.siteName || b.siteId));
  };

  const getIssueDraft = (country) => {
    return issueDrafts[country] || {
      storeId: '',
      storeName: '',
      description: '',
      priority: '',
      responsibleParty: '',
      actionRequired: '',
      resolveDate: '',
    };
  };

  const getIssueEditTarget = (country) => issueEditTargets[country] || null;

  const startIssueEdit = (country, entry) => {
    if (!entry?.id) return;
    setIssueEditTargets((prev) => ({ ...prev, [country]: entry.id }));
    updateIssueDraft(country, {
      storeId: entry.storeId || '',
      storeName: entry.storeName || '',
      description: entry.description || '',
      priority: entry.priority || '',
      responsibleParty: entry.responsibleParty || '',
      actionRequired: entry.actionRequired || '',
      resolveDate: entry.resolveDate || '',
    });
  };

  const clearIssueEdit = (country) => {
    setIssueEditTargets((prev) => {
      const next = { ...prev };
      delete next[country];
      return next;
    });
  };

  const updateIssueDraft = (country, patch) => {
    setIssueDrafts((prev) => ({
      ...prev,
      [country]: { ...getIssueDraft(country), ...patch },
    }));
  };

  const submitIssue = async (country) => {
    const draft = getIssueDraft(country);
    const editingId = getIssueEditTarget(country);
    setIssueSaving((prev) => ({ ...prev, [country]: true }));
    try {
      const response = await fetch(
        editingId
          ? `/api/smartsheet/presentation/issues/${editingId}`
          : '/api/smartsheet/presentation/issues',
        {
          method: editingId ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            country,
            storeId: draft.storeId || null,
            storeName: draft.storeName || null,
            description: draft.description || null,
            priority: draft.priority || null,
            responsibleParty: draft.responsibleParty || null,
            actionRequired: draft.actionRequired || null,
            resolveDate: draft.resolveDate || null,
          }),
        }
      );
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
      updateIssueDraft(country, {
        storeId: '',
        storeName: '',
        description: '',
        priority: '',
        responsibleParty: '',
        actionRequired: '',
        resolveDate: '',
      });
      if (editingId) {
        clearIssueEdit(country);
      }
      fetchPresentation();
    } catch (error) {
      setPresentationSaveError(error.message || 'Failed to save issue.');
    } finally {
      setIssueSaving((prev) => ({ ...prev, [country]: false }));
    }
  };

  const resetTaskTrackerDraft = () => {
    setTaskTrackerDraft({ date: formatYmd(new Date()), category: '', description: '', responsible: '', tasks: [] });
    setTaskTrackerEditId(null);
  };

  const normalizeTaskTrackerDate = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    const match = raw.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})$/);
    if (match) {
      const day = String(match[1]).padStart(2, '0');
      const month = String(match[2]).padStart(2, '0');
      const year = match[3];
      return `${year}-${month}-${day}`;
    }
    return raw;
  };

  function normalizeTaskTrackerTasks(tasks, taskIdLookup = null) {
    if (!Array.isArray(tasks)) return [];
    return tasks.map((task) => {
      if (task && typeof task === 'object') {
        const taskId = task.taskId ?? task.id ?? task.task_id ?? null;
        const label = task.label
          ?? task.taskName
          ?? task.task_name
          ?? task.name
          ?? (taskId ? String(taskId) : '');
        const resolvedTaskId = taskId ?? (taskIdLookup && label ? taskIdLookup.get(String(label)) : null);
        return {
          taskId: resolvedTaskId !== null ? String(resolvedTaskId) : null,
          label: String(label || ''),
          country: task.country ?? null,
          siteName: task.siteName ?? task.site_name ?? null,
          taskName: task.taskName ?? task.task_name ?? null,
        };
      }
      const label = String(task);
      const resolvedTaskId = taskIdLookup && label ? taskIdLookup.get(label) : null;
      return { taskId: resolvedTaskId !== null ? String(resolvedTaskId) : null, label };
    }).filter((item) => item.label);
  }

  function getTaskTrackerTaskKey(task) {
    if (!task) return '';
    if (typeof task === 'object') {
      return String(task.taskId ?? task.id ?? task.task_id ?? task.value ?? task.label ?? '');
    }
    return String(task);
  }

  function getTaskTrackerTaskLabel(task) {
    if (!task) return '';
    if (typeof task === 'object') {
      return String(task.label ?? task.taskName ?? task.task_name ?? task.name ?? task.taskId ?? '');
    }
    return String(task);
  }

  const cleanTaskTrackerSiteName = (value) => {
    if (!value) return '';
    return String(value)
      .replace(/IKEAStore\s*-\s*/gi, '')
      .replace(/IKEA\s*-\s*/gi, '')
      .replace(/Store\s*-\s*/gi, '')
      .replace(/\bIKEAStore\b/gi, '')
      .replace(/\bIKEA\b/gi, '')
      .replace(/\bStore\b/gi, '')
      .replace(/^\s*-\s*/g, '')
      .replace(/\s*-\s*/g, ' ')
      .trim();
  };

  const renderTaskTrackerTask = (task, index) => {
    const label = getTaskTrackerTaskLabel(task);
    const parts = label.split(' / ').map((part) => part.trim()).filter(Boolean);
    const country = (task && typeof task === 'object' && task.country) ? task.country : (parts[0] || '');
    const siteName = (task && typeof task === 'object' && task.siteName)
      ? task.siteName
      : (parts[1] || '');
    const taskName = (task && typeof task === 'object' && task.taskName)
      ? task.taskName
      : (parts[2] || parts.slice(2).join(' / '));

    const cleanedSite = cleanTaskTrackerSiteName(siteName);
    const textParts = [cleanedSite, taskName].filter(Boolean).join(' / ');

    return (
      <div key={`task-tracker-task-${index}`} className="d-flex align-items-center gap-2">
        {country ? <CountryFlag country={country} /> : null}
        <span>{textParts}</span>
      </div>
    );
  };

  const startTaskTrackerEdit = (entry) => {
    if (!entry?.id) return;
    setTaskTrackerEditId(entry.id);
    setTaskTrackerDraft({
      date: normalizeTaskTrackerDate(entry.date) || '',
      category: entry.category || '',
      description: entry.description || '',
      responsible: entry.responsible || '',
      tasks: normalizeTaskTrackerTasks(entry.tasks, taskTrackerTaskIdLookup),
    });
  };

  const updateTaskTrackerDraft = (patch) => {
    setTaskTrackerDraft((prev) => ({ ...prev, ...patch }));
  };

  const submitTaskTracker = async () => {
    const normalizedDate = normalizeTaskTrackerDate(taskTrackerDraft.date);
    if (!normalizedDate || !/^\d{4}-\d{2}-\d{2}$/.test(normalizedDate)) {
      setTaskTrackerError('Date must be in YYYY-MM-DD format.');
      return;
    }
    if (!taskTrackerDraft.category || !taskTrackerDraft.description) {
      setTaskTrackerError('Date, category, and description are required.');
      return;
    }
    const tasksPayload = normalizeTaskTrackerTasks(taskTrackerDraft.tasks, taskTrackerTaskIdLookup).map((task) => ({
      taskId: task.taskId,
      label: task.label,
      country: task.country ?? null,
      siteName: task.siteName ?? null,
      taskName: task.taskName ?? null,
    }));
    setTaskTrackerSaving(true);
    setTaskTrackerError(null);
    try {
      const endpoint = taskTrackerEditId
        ? `/api/smartsheet/presentation/task-tracker/${taskTrackerEditId}`
        : '/api/smartsheet/presentation/task-tracker';
      const method = taskTrackerEditId ? 'PUT' : 'POST';
      const response = await fetch(endpoint, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          date: normalizedDate,
          category: taskTrackerDraft.category,
          description: taskTrackerDraft.description,
          responsible: taskTrackerDraft.responsible || null,
          tasks: tasksPayload,
        }),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
      resetTaskTrackerDraft();
      fetchTaskTracker();
    } catch (error) {
      setTaskTrackerError(error.message || 'Failed to save task tracker entry.');
    } finally {
      setTaskTrackerSaving(false);
    }
  };

  const scopeCategoryMap = {
    'assessments-current': 'assessment',
    'assessments-next': 'assessment',
    'installations-current': 'installation',
    'installations-next': 'installation',
    'postdeployment-current': 'post_deployment',
    'postdeployment-next': 'post_deployment',
  };

  const presentationEntryKey = (entry, scope, country) => [
    scope,
    country ?? '',
    entry.siteId ?? '',
    entry.siteName ?? '',
    entry.startDate ?? '',
    entry.endDate ?? '',
  ].join('||');

  const getPresentationDraft = (entry, scope, country) => {
    const key = presentationEntryKey(entry, scope, country);
    return presentationEdits[key] || {
      confidence: entry.confidence ?? '',
      status: entry.status ?? '',
      country,
      siteId: entry.siteId ?? '',
      siteName: entry.siteName ?? '',
      category: scopeCategoryMap[scope] || '',
    };
  };

  const updatePresentationDraft = (entry, scope, country, patch) => {
    const key = presentationEntryKey(entry, scope, country);
    const base = getPresentationDraft(entry, scope, country);
    setPresentationEdits((prev) => ({
      ...prev,
      [key]: { ...base, ...patch },
    }));
  };

  const savePresentationEdits = async () => {
    const edits = Object.values(presentationEdits);
    const trendDraftEntries = Object.entries(trendDrafts);
    const overviewDraftEntries = Object.entries(overviewDrafts);
    const generalIssueDraftEntries = Object.entries(generalIssuesDrafts);
    const plannedWeekDraftEntries = Object.entries(plannedWeekCommentDrafts);
    if (edits.length === 0 && trendDraftEntries.length === 0 && overviewDraftEntries.length === 0 && generalIssueDraftEntries.length === 0 && plannedWeekDraftEntries.length === 0) {
      setPresentationSaveError('No changes to save.');
      return;
    }

    setPresentationSaving(true);
    setTrendSaving(true);
    setOverviewOverridesSaving(true);
    setPresentationSaveError(null);

    try {
      if (edits.length > 0) {
        await Promise.all(
          edits.map((edit) =>
            fetch('/api/smartsheet/presentation/status/save', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                country: edit.country,
                siteId: edit.siteId,
                siteName: edit.siteName || null,
                category: edit.category,
                ragConfidence: edit.confidence || null,
                statusText: edit.status || '',
              }),
            }).then(async (response) => {
              if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload?.message || `HTTP ${response.status}`);
              }
            })
          )
        );
        setPresentationEdits({});
      }

      if (trendDraftEntries.length > 0) {
        const nextOverrides = { ...trendOverrides };
        trendDraftEntries.forEach(([country, draft]) => {
          nextOverrides[country] = {
            rag: draft?.rag ?? null,
            comment: draft?.comment ?? '',
          };
        });

        const response = await fetch('/api/smartsheet/presentation/content', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            section: 'trend_overrides',
            content: JSON.stringify(nextOverrides),
          }),
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(payload?.message || `HTTP ${response.status}`);
        }

        setTrendOverrides(nextOverrides);
        setTrendDrafts({});
      }

      if (overviewDraftEntries.length > 0) {
        const nextOverrides = { ...overviewOverrides };
        overviewDraftEntries.forEach(([country, draft]) => {
          nextOverrides[country] = {
            rag: draft?.rag ?? null,
            comment: draft?.comment ?? '',
          };
        });

        const response = await fetch('/api/smartsheet/presentation/content', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            section: 'overview_overrides',
            content: JSON.stringify(nextOverrides),
          }),
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(payload?.message || `HTTP ${response.status}`);
        }

        setOverviewOverrides(nextOverrides);
        setOverviewDrafts({});
      }

      if (generalIssueDraftEntries.length > 0) {
        const nextOverrides = { ...generalIssuesOverrides };
        generalIssueDraftEntries.forEach(([issueId, show]) => {
          nextOverrides[issueId] = { show: !!show };
        });

        const response = await fetch('/api/smartsheet/presentation/content', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            section: 'general_issues_overrides',
            content: JSON.stringify(nextOverrides),
          }),
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(payload?.message || `HTTP ${response.status}`);
        }

        setGeneralIssuesOverrides(nextOverrides);
        setGeneralIssuesDrafts({});
      }

      if (plannedWeekDraftEntries.length > 0) {
        const nextOverrides = { ...plannedWeekCommentOverrides };
        plannedWeekDraftEntries.forEach(([key, value]) => {
          nextOverrides[key] = value;
        });

        const response = await fetch('/api/smartsheet/presentation/content', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            section: 'planned_week_comments',
            content: JSON.stringify(nextOverrides),
          }),
        });

        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(payload?.message || `HTTP ${response.status}`);
        }

        setPlannedWeekCommentOverrides(nextOverrides);
        setPlannedWeekCommentDrafts({});
      }

      setPresentationEditMode(false);
      if (edits.length > 0) {
        fetchPresentation();
      }
    } catch (error) {
      setPresentationSaveError(error.message || 'Failed to save presentation edits.');
    } finally {
      setPresentationSaving(false);
      setTrendSaving(false);
      setOverviewOverridesSaving(false);
    }
  };

  const updateStatusDraft = (country, siteId, categoryId, patch) => {
    const key = statusKey(country, siteId);
    setStatusDrafts((prev) => {
      const current = prev[key] || {};
      const nextCategory = { ...(current[categoryId] || {}), ...patch };
      return { ...prev, [key]: { ...current, [categoryId]: nextCategory } };
    });
  };

  const getStatusDraft = (country, siteId, categoryId) => {
    const key = statusKey(country, siteId);
    return statusDrafts[key]?.[categoryId] || { ragConfidence: '', statusText: '', saving: false, error: null };
  };

  const submitStatusLog = async (site, categoryId) => {
    const draft = getStatusDraft(site.country, site.siteId, categoryId);
    const statusText = (draft.statusText || '').trim();
    if (!statusText) {
      updateStatusDraft(site.country, site.siteId, categoryId, { error: 'Status text is required.' });
      return;
    }

    updateStatusDraft(site.country, site.siteId, categoryId, { saving: true, error: null });

    try {
      const response = await fetch('/api/smartsheet/presentation/status/log', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          country: site.country,
          siteId: site.siteId,
          siteName: site.siteName,
          category: categoryId,
          ragConfidence: draft.ragConfidence || null,
          statusText,
        }),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
      updateStatusDraft(site.country, site.siteId, categoryId, { statusText: '', saving: false });
      fetchStatus();
    } catch (error) {
      updateStatusDraft(site.country, site.siteId, categoryId, { saving: false, error: error.message || 'Failed to save status.' });
    }
  };

  const renderAssessmentTable = (entries, scope, country) => {
    if (!entries || entries.length === 0) {
      return <div className="text-muted small">No planned assessments.</div>;
    }

    return (
      <div className="table-responsive">
        <table className="table table-sm table-bordered table-striped align-middle mb-0">
          <colgroup>
            <col style={{ width: '26%' }} />
            <col style={{ width: '12%' }} />
            <col style={{ width: '12%' }} />
            <col style={{ width: '12%' }} />
            <col style={{ width: '38%' }} />
          </colgroup>
          <thead className="table-light">
            <tr>
              <th>Site Name</th>
              <th>Start</th>
              <th>End</th>
              <th>Confidence</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {entries.map((entry, index) => (
              <tr key={`${entry.siteId ?? 'site'}-${index}`}>
                <td>
                  {formatDisplayValue(entry.siteName)}
                  {entry.siteId ? ` (${entry.siteId})` : ''}
                </td>
                <td>{formatDateDisplay(entry.startDate)}</td>
                <td>{formatDateDisplay(entry.endDate)}</td>
                <td>
                  {presentationEditMode ? (
                    <select
                      className="form-select form-select-sm"
                      value={getPresentationDraft(entry, scope, country).confidence}
                      onChange={(event) =>
                        updatePresentationDraft(entry, scope, country, { confidence: event.target.value })
                      }
                      style={{
                        backgroundColor: confidenceColor(getPresentationDraft(entry, scope, country).confidence),
                        color: getPresentationDraft(entry, scope, country).confidence ? '#fff' : undefined,
                      }}
                    >
                      <option value="">—</option>
                      <option value="High">High</option>
                      <option value="Medium">Medium</option>
                      <option value="Low">Low</option>
                    </select>
                  ) : (
                    (getPresentationDraft(entry, scope, country).confidence || entry.confidence) ? (
                      <span className={`badge bg-${confidenceVariant(getPresentationDraft(entry, scope, country).confidence || entry.confidence)}`}>
                        {formatDisplayValue(getPresentationDraft(entry, scope, country).confidence || entry.confidence)}
                      </span>
                    ) : (
                      '—'
                    )
                  )}
                </td>
                <td>
                  {presentationEditMode ? (
                    <textarea
                      className="form-control form-control-sm"
                      rows={2}
                      value={getPresentationDraft(entry, scope, country).status}
                      onChange={(event) =>
                        updatePresentationDraft(entry, scope, country, { status: event.target.value })
                      }
                      placeholder="Status"
                    />
                  ) : (
                    formatDisplayValue(getPresentationDraft(entry, scope, country).status || entry.status)
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  const renderIssueTable = (entries, country) => {
    if (!entries || entries.length === 0) {
      return <div className="text-muted small">No issues logged.</div>;
    }

    return (
      <div className="table-responsive">
        <table className="table table-sm table-bordered table-striped align-middle mb-0">
          <thead className="table-light">
            <tr>
              <th>Site Name</th>
              <th>Site ID</th>
              <th>Description</th>
              <th>Priority (CHML)</th>
              <th>Responsible Party</th>
              <th>Action to be taken (DD.MM.YY - NS)</th>
              <th>Date to be resolved (DD.MM.YY)</th>
              {presentationEditMode && <th />}
            </tr>
          </thead>
          <tbody>
            {entries.map((entry, index) => (
              <tr key={`${entry.storeId ?? 'store'}-${index}`}>
                <td>{formatDisplayValue(entry.storeName)}</td>
                <td>{formatDisplayValue(entry.storeId)}</td>
                <td>{formatDisplayValue(entry.description)}</td>
                <td>{formatDisplayValue(entry.priority)}</td>
                <td>{formatDisplayValue(entry.responsibleParty)}</td>
                <td>{formatDisplayValue(entry.actionRequired)}</td>
                <td>{formatDateDisplay(entry.resolveDate)}</td>
                {presentationEditMode && (
                  <td className="text-nowrap">
                    {entry.id ? (
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary"
                        onClick={() => startIssueEdit(country, entry)}
                      >
                        Edit
                      </button>
                    ) : (
                      <span className="text-muted small">—</span>
                    )}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  const renderGeneralIssuesTable = (entries, title) => {
    const visibleEntries = presentationEditMode
      ? entries
      : entries.filter((entry) => getGeneralIssueShow(entry));

    if (!visibleEntries || visibleEntries.length === 0) {
      return (
        <div className="d-flex flex-column gap-2">
          <div className="fw-semibold">{title}</div>
          <div className="text-muted small">No issues logged.</div>
        </div>
      );
    }

    return (
      <div className="d-flex flex-column gap-2">
        <div className="fw-semibold">{title}</div>
        <div className="table-responsive">
          <table className="table table-sm table-bordered table-striped align-middle mb-0 w-100">
            <colgroup>
              <col style={{ width: '35%' }} />
              <col style={{ width: '12%' }} />
              <col style={{ width: '10%' }} />
              <col style={{ width: '39%' }} />
              <col style={{ width: '4%' }} />
            </colgroup>
            <thead className="table-light">
              <tr>
                <th>Description</th>
                <th>Country</th>
                <th>Owner</th>
                <th>Action</th>
                <th>Priority (CHML)</th>
                {presentationEditMode && <th>Show</th>}
              </tr>
            </thead>
            <tbody>
              {visibleEntries.map((entry, index) => (
                <tr key={`${entry.country ?? 'country'}-${index}`}>
                  <td>{formatDisplayValue(entry.description)}</td>
                  <td className="fw-semibold">
                    {entry.country ? <CountryFlag country={entry.country} /> : null}
                    {formatDisplayValue(entry.country)}
                  </td>
                  <td>{formatDisplayValue(entry.owner)}</td>
                  <td>
                    {formatActionLines(entry.action).map((line, lineIndex) => (
                      <div key={`${entry.country ?? 'country'}-${index}-action-${lineIndex}`}>{line}</div>
                    ))}
                  </td>
                  <td>{formatDisplayValue(entry.priority)}</td>
                  {presentationEditMode && (
                    <td className="text-center">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        checked={getGeneralIssueShow(entry)}
                        onChange={(event) => updateGeneralIssueDraft(entry, event.target.checked)}
                      />
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  };

  const execCardMeta = [
    { key: '__exec_highlights', title: 'Highlights', body: 'Key wins, risks, and milestones.' },
    { key: '__exec_overview', title: 'Programme Overview Per Country', body: 'Summary of progress and key highlights per country.' },
    { key: '__exec_status', title: 'Status planned assessments and installations', body: 'Snapshot of planned assessments and installations status.' },
    { key: '__exec_timeline', title: 'Timeline', body: 'High-level milestones and upcoming dates.' },
    { key: '__trend_green', title: 'Country Trend: Green', body: 'Countries currently on track.' },
    { key: '__trend_amber', title: 'Country Trend: Amber', body: 'Countries with risks or minor delays.' },
    { key: '__trend_red', title: 'Country Trend: Red', body: 'Countries with critical issues or delays.' },
    { key: '__issues', title: 'General Issues', body: 'Cross-country issues and blockers.' },
  ];

  const openSlideshow = () => {
    setSlideshowIndex(0);
    setSlideshowOpen(true);
  };

  const closeSlideshow = () => {
    setSlideshowOpen(false);
  };

  const slideshowItems = [
    ...execCardMeta.map((meta) => ({ type: 'exec', key: meta.key, title: meta.title, meta })),
    ...filteredPresentationItems.map((countryBlock) => ({
      type: 'country',
      key: countryBlock.country,
      title: countryBlock.country,
      countryBlock,
    })),
  ];
  const slideshowItem = slideshowItems[slideshowIndex] || null;

  const findProgressForCountry = (country) => {
    const items = Array.isArray(presentationProgress?.items) ? presentationProgress.items : [];
    return items.find((item) => item.country === country) || null;
  };

  const renderProgressRow = (task) => {
    const total = Number(task.total) || 0;
    const donePct = Number(task.donePct) || 0;
    const inProgressPct = Number(task.inProgressPct) || 0;
    const notStartedPct = Number(task.notStartedPct) || 0;

    return (
      <div key={task.key} className="d-flex flex-column gap-1">
        <div className="d-flex justify-content-between small">
          <span className="fw-semibold">{task.label}</span>
          <span className="text-muted">
            {total > 0
              ? `Done ${donePct}% · In progress ${inProgressPct}% · Not started ${notStartedPct}%`
              : 'No stores'}
          </span>
        </div>
        <div className="progress" style={{ height: 8 }}>
          <div
            className="progress-bar bg-success"
            role="progressbar"
            style={{ width: `${donePct}%` }}
            aria-label={`${task.label} done`}
          />
          <div
            className="progress-bar bg-warning"
            role="progressbar"
            style={{ width: `${inProgressPct}%` }}
            aria-label={`${task.label} in progress`}
          />
          <div
            className="progress-bar bg-secondary"
            role="progressbar"
            style={{ width: `${notStartedPct}%` }}
            aria-label={`${task.label} not started`}
          />
        </div>
      </div>
    );
  };

  const saveHighlights = async () => {
    setHighlightsSaving(true);
    setHighlightsError(null);
    try {
      const response = await fetch('/api/smartsheet/presentation/content', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ section: 'highlights', content: highlightsContent || '' }),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
    } catch (error) {
      setHighlightsError(error.message || 'Failed to save highlights.');
    } finally {
      setHighlightsSaving(false);
    }
  };

  const renderCountryMedals = (country) => {
    const progress = findProgressForCountry(country);
    if (!progress || !Array.isArray(progress.tasks)) {
      return null;
    }
    const medals = progress.tasks.filter((task) => Number(task.total) > 0 && Number(task.donePct) === 100);
    if (medals.length === 0) {
      return null;
    }
    return (
      <span className="ms-2 small" title="100% complete">
        {medals.map((task) => (
          <span key={task.key} className="me-2">🥇 {task.label}</span>
        ))}
      </span>
    );
  };

  const normalizeRag = (value) => {
    const text = String(value || '').trim().toLowerCase();
    if (!text) {
      return '';
    }
    if (text.includes('green')) {
      return 'green';
    }
    if (text.includes('amber') || text.includes('yellow')) {
      return 'amber';
    }
    if (text.includes('red')) {
      return 'red';
    }
    return '';
  };

  const getTrendDraft = (country) => trendDrafts[country] || { rag: null, comment: null };

  const updateTrendDraft = (country, patch) => {
    setTrendDrafts((prev) => {
      const current = prev[country] || {};
      return { ...prev, [country]: { ...current, ...patch } };
    });
  };

  const getOverviewDraft = (country) => overviewDrafts[country] || { rag: null, comment: null };

  const updateOverviewDraft = (country, patch) => {
    setOverviewDrafts((prev) => {
      const current = prev[country] || {};
      return { ...prev, [country]: { ...current, ...patch } };
    });
  };

  const formatPlannedWeekDateKey = (value) => {
    if (!value) return '';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return formatYmd(date);
  };

  const buildPlannedWeekCommentKey = (country, siteId, siteName, taskName, startDate, endDate) => {
    const siteKey = siteId || siteName || '';
    return [
      String(country || ''),
      String(siteKey || ''),
      String(taskName || ''),
      formatPlannedWeekDateKey(startDate),
      formatPlannedWeekDateKey(endDate),
    ].join('||').toLowerCase();
  };

  const getPlannedWeekCommentDraft = (key) => {
    if (Object.prototype.hasOwnProperty.call(plannedWeekCommentDrafts, key)) {
      return plannedWeekCommentDrafts[key];
    }
    if (Object.prototype.hasOwnProperty.call(plannedWeekCommentOverrides, key)) {
      return plannedWeekCommentOverrides[key];
    }
    return '';
  };

  const updatePlannedWeekCommentDraft = (key, value) => {
    setPlannedWeekCommentDrafts((prev) => ({ ...prev, [key]: value }));
  };

  const getGeneralIssueShow = (entry) => {
    const key = entry?.id ? String(entry.id) : null;
    if (!key) return true;
    if (Object.prototype.hasOwnProperty.call(generalIssuesDrafts, key)) {
      return !!generalIssuesDrafts[key];
    }
    const override = generalIssuesOverrides[key];
    if (override && typeof override.show !== 'undefined') {
      return !!override.show;
    }
    return true;
  };

  const updateGeneralIssueDraft = (entry, show) => {
    if (!entry?.id) return;
    const key = String(entry.id);
    setGeneralIssuesDrafts((prev) => ({ ...prev, [key]: !!show }));
  };

  const trendItems = filteredOverviewItems.map((row) => {
    const override = trendOverrides?.[row.country] || {};
    return {
      ...row,
      rag: override.rag ?? null,
      comment: override.comment ?? null,
    };
  });

  const trendGroups = trendItems.reduce(
    (acc, row) => {
      const rag = normalizeRag(row.rag);
      if (rag && acc[rag]) {
        acc[rag].push(row);
      }
      return acc;
    },
    { green: [], amber: [], red: [] }
  );

  const generalIssues = presentationIssues?.generalIssues || { ikea: [], hpe: [] };
  const normalizeCountryMatch = (value) => String(value || '').toLowerCase();
  const sortGeneralIssues = (entries) => {
    const items = [...entries];
    items.sort((a, b) => {
      const aCountry = String(a.country || '');
      const bCountry = String(b.country || '');
      const aIsGeneral = aCountry.toLowerCase() === 'general';
      const bIsGeneral = bCountry.toLowerCase() === 'general';
      if (aIsGeneral && !bIsGeneral) return -1;
      if (!aIsGeneral && bIsGeneral) return 1;
      return aCountry.localeCompare(bCountry);
    });
    return items;
  };

  const filterGeneralIssues = (entries) => {
    const filtered = !selectedCountryValues.length
      ? entries
      : entries.filter((entry) =>
          selectedCountryValues.some((country) => normalizeCountryMatch(entry.country).includes(normalizeCountryMatch(country)))
        );
    return sortGeneralIssues(filtered);
  };

  

  useEffect(() => {
    if (activeTab === 'presentation' && !presentationLoaded && !presentationLoading) {
      fetchPresentation();
    }
  }, [activeTab, fetchPresentation, presentationLoaded, presentationLoading]);

  useEffect(() => {
    if (activeTab === 'presentation') {
      fetchHighlights();
      fetchTrendOverrides();
      fetchOverviewOverrides();
      fetchGeneralIssuesOverrides();
      fetchPlannedWeekCommentOverrides();
    }
  }, [activeTab, fetchHighlights, fetchTrendOverrides, fetchOverviewOverrides, fetchGeneralIssuesOverrides, fetchPlannedWeekCommentOverrides]);

  const onPresentationCountryChange = (event) => {
    const selected = Array.from(event.target.selectedOptions).map((option) => option.value);
    setPresentationCountryFilter(selected);
  };

  const clearTimelineDrilldown = () => setTimelineDrilldownCountry(null);

  const renderExecCardBody = (meta) => {
    if (meta.key === '__exec_highlights') {
      return (
        <div className="d-flex flex-column gap-3">
          {highlightsLoading && (
            <div className="text-muted small">Loading highlights...</div>
          )}
          {highlightsError && (
            <div className="alert alert-warning py-2 mb-0" role="alert">
              {highlightsError}
            </div>
          )}
          {!highlightsLoading && !highlightsError && (
            <div style={{ display: presentationEditMode ? 'block' : 'none' }}>
              <TrumboField
                value={highlightsContent}
                onChange={(value) => setHighlightsContent(value || '')}
                placeholder="Add key wins, risks, and milestones..."
              />
              <div className="d-flex justify-content-end">
                <button
                  type="button"
                  className="btn btn-sm btn-primary"
                  onClick={saveHighlights}
                  disabled={highlightsSaving}
                >
                  {highlightsSaving ? 'Saving…' : 'Save highlights'}
                </button>
              </div>
            </div>
          )}
          {!highlightsLoading && !highlightsError && !presentationEditMode && (
            highlightsContent ? (
              <div
                className="presentation-highlight-content"
                dangerouslySetInnerHTML={{ __html: highlightsContent }}
              />
            ) : (
              <div className="text-muted small">No highlights yet.</div>
            )
          )}
        </div>
      );
    }

    if (meta.key === '__exec_overview') {
      return (
        <div className="d-flex flex-column gap-2">
          {overviewLoading && (
            <div className="text-muted small">Loading overview...</div>
          )}
          {overviewError && (
            <div className="alert alert-warning py-2 mb-0" role="alert">
              {overviewError}
            </div>
          )}
          {overviewOverridesLoading && (
            <div className="text-muted small">Loading overview overrides...</div>
          )}
          {overviewOverridesError && (
            <div className="alert alert-warning py-2 mb-0" role="alert">
              {overviewOverridesError}
            </div>
          )}
          {!overviewLoading && !overviewError && filteredOverviewItems.length === 0 && (
            <div className="text-muted small">No overview data available.</div>
          )}
          {!overviewLoading && !overviewError && filteredOverviewItems.length > 0 && (
            <div className="table-responsive">
              <table className="table table-sm table-bordered table-striped align-middle mb-0">
                <colgroup>
                  <col style={{ width: '26%' }} />
                  <col style={{ width: '9%' }} />
                  <col style={{ width: '9%' }} />
                  <col style={{ width: '12%' }} />
                  <col style={{ width: '9%' }} />
                  <col style={{ width: '9%' }} />
                  <col style={{ width: '8%' }} />
                  <col style={{ width: '18%' }} />
                </colgroup>
                <thead className="table-light">
                  <tr>
                    <th>Country</th>
                    <th>Stores</th>
                    <th>Assessed</th>
                    <th>Ongoing Installations</th>
                    <th>Installed</th>
                    <th>Sign-off</th>
                    <th>RAG</th>
                    <th>Comment</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredOverviewItems.map((row) => {
                    const override = overviewOverrides?.[row.country] || {};
                    const draft = getOverviewDraft(row.country);
                    const ragValue = draft.rag !== null && draft.rag !== undefined ? draft.rag : (override.rag ?? row.rag ?? '');
                    const commentValue = draft.comment !== null && draft.comment !== undefined ? draft.comment : (override.comment ?? row.comment ?? '');
                    const rag = normalizeRag(ragValue);
                    const ragLabel = ragValue ? ragValue : '—';
                    const ragClass = rag === 'green'
                      ? 'success'
                      : rag === 'amber'
                        ? 'warning text-dark'
                        : rag === 'red'
                          ? 'danger'
                          : 'secondary';
                    return (
                      <tr key={row.country}>
                        <td className="fw-semibold">
                          <CountryAnchor country={row.country} />
                        </td>
                        <td>{formatDisplayValue(row.stores)}</td>
                        <td>{formatDisplayValue(row.assessed)}</td>
                        <td>{formatDisplayValue(row.ongoingInstallations)}</td>
                        <td>{formatDisplayValue(row.storesInstalled)}</td>
                        <td>{formatDisplayValue(row.storeSignoff)}</td>
                        <td>
                          {presentationEditMode ? (
                            <select
                              className="form-select form-select-sm"
                              value={ragValue || ''}
                              onChange={(event) => updateOverviewDraft(row.country, { rag: event.target.value })}
                            >
                              <option value="">—</option>
                              <option value="Green">Green</option>
                              <option value="Amber">Amber</option>
                              <option value="Red">Red</option>
                            </select>
                          ) : (
                            <span className={`badge bg-${ragClass}`}>
                              {formatDisplayValue(ragLabel)}
                            </span>
                          )}
                        </td>
                        <td className="text-muted small">
                          {presentationEditMode ? (
                            <input
                              type="text"
                              className="form-control form-control-sm"
                              value={commentValue ?? ''}
                              onChange={(event) => updateOverviewDraft(row.country, { comment: event.target.value })}
                            />
                          ) : (
                            commentValue ? formatDisplayValue(commentValue) : ''
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      );
    }

    if (meta.key === '__exec_status') {
      return (
        <div className="d-flex flex-column gap-2">
          {plannedWeekLoading && (
            <div className="text-muted small">Loading status snapshot...</div>
          )}
          {plannedWeekError && (
            <div className="alert alert-warning py-2 mb-0" role="alert">
              {plannedWeekError}
            </div>
          )}
          {!plannedWeekLoading && !plannedWeekError && plannedWeekRows.length === 0 && (
            <div className="text-muted small">No planned assessments or installations found.</div>
          )}
          {!plannedWeekLoading && !plannedWeekError && plannedWeekRows.length > 0 && (
            <div className="table-responsive">
              <table className="table table-sm table-bordered table-striped align-middle mb-0">
                <colgroup>
                  <col style={{ width: '18%' }} />
                  <col style={{ width: '24%' }} />
                  <col style={{ width: '16%' }} />
                  <col style={{ width: '10%' }} />
                  <col style={{ width: '10%' }} />
                  <col style={{ width: '12%' }} />
                  <col style={{ width: '10%' }} />
                </colgroup>
                <thead className="table-light">
                  <tr>
                    <th>Country</th>
                    <th>Site Name (Site ID)</th>
                    <th>Activity</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Comment</th>
                  </tr>
                </thead>
                <tbody>
                  {plannedWeekRows.map((row, index) => {
                    const country = getRowField(row, ['country', 'Country']) || '—';
                    const siteName = cleanSiteName(getRowField(row, ['site_name', 'siteName', 'Site_Name', 'SiteName']) || '');
                    const siteId = getRowField(row, ['site_id', 'siteId', 'Site_ID', 'SiteID']) || '';
                    const taskName = getRowField(row, ['task_name', 'taskName', 'Task_Name', 'TaskName']) || '—';
                    const startDate = getRowField(row, ['start_date', 'startDate', 'Start_Date', 'StartDate']);
                    const endDate = getRowField(row, ['end_date', 'endDate', 'End_Date', 'EndDate']);
                    const status = getRowField(row, ['status', 'Status']) || '';
                    const comment = getRowField(row, ['comment', 'Comment']) || '';
                    const commentKey = buildPlannedWeekCommentKey(country, siteId, siteName, taskName, startDate, endDate);
                    const commentValue = getPlannedWeekCommentDraft(commentKey) || '';

                    return (
                      <tr key={`${country}-${siteId || siteName || 'row'}-${index}`}>
                        <td className="fw-semibold">
                          <CountryAnchor country={country} />
                        </td>
                        <td>
                          {siteName || '—'}
                          {siteId ? ` (${siteId})` : ''}
                        </td>
                        <td>{formatDisplayValue(taskName)}</td>
                        <td>{formatDateDisplay(startDate)}</td>
                        <td>{formatDateDisplay(endDate)}</td>
                        <td>{formatDisplayValue(status)}</td>
                        <td className="text-muted small">
                          {presentationEditMode ? (
                            <textarea
                              className="form-control form-control-sm"
                              rows={2}
                              value={commentValue}
                              onChange={(event) => updatePlannedWeekCommentDraft(commentKey, event.target.value)}
                            />
                          ) : (
                            formatDisplayValue(comment)
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      );
    }

    if (meta.key === '__exec_timeline') {
      const buildCountryTimelineOptions = () => {
        if (!timelineDomain) return null;
        const categories = timelineItems.map((item) => item.country || 'Unspecified');
        const installData = [];
        const signoffData = [];

        timelineItems.forEach((item, index) => {
          const country = item.country || 'Unspecified';
          const start = parseDateValue(item.startDate);
          const installEnd = parseDateValue(item.installEndDate || item.endDate);
          const end = parseDateValue(item.endDate);
          if (!start || !end) return;
          const installEndDate = installEnd && installEnd > start ? installEnd : end;
          installData.push({ x: start.getTime(), x2: installEndDate.getTime(), y: index, country });
          if (end.getTime() > installEndDate.getTime()) {
            signoffData.push({ x: installEndDate.getTime(), x2: end.getTime(), y: index, country });
          }
        });

        return {
          chart: {
            type: 'xrange',
            height: Math.max(320, categories.length * 32 + 120),
            spacingLeft: 10,
            spacingRight: 10,
          },
          accessibility: { enabled: false },
          title: { text: null },
          credits: { enabled: false },
          legend: { enabled: true },
          xAxis: {
            type: 'datetime',
            min: timelineDomain.min,
            max: timelineDomain.max,
            plotLines: timelineDomain.now !== null
              ? [{ color: '#1f3b64', width: 2, value: timelineDomain.now, zIndex: 5 }]
              : [],
          },
          yAxis: {
            categories,
            reversed: true,
            title: { text: null },
            labels: {
              useHTML: true,
              style: { fontWeight: 600 },
              formatter() {
                const label = String(this.value || '');
                const code = countryFlagCode(label);
                if (!code) {
                  return label;
                }
                const src = `https://flagcdn.com/16x12/${code.toLowerCase()}.png`;
                return `<span style="display:inline-flex;align-items:center;gap:6px;">
                  <img src="${src}" alt="" width="16" height="12" style="border-radius:2px;" />
                  <span>${label}</span>
                </span>`;
              },
            },
          },
          tooltip: {
            formatter() {
              const category = categories[this.point.y] || this.point.country || 'Unspecified';
              const startLabel = formatYmd(new Date(this.point.x));
              const endLabel = formatYmd(new Date(this.point.x2));
              return `<b>${category}</b><br/>${this.series.name}: ${startLabel} → ${endLabel}`;
            },
          },
          plotOptions: {
            series: {
              borderRadius: 4,
              pointPadding: 0.15,
              groupPadding: 0.12,
              colorByPoint: false,
              point: {
                events: {
                  click() {
                    const target = this.country || categories[this.y] || 'Unspecified';
                    const sites = timelineSiteMap.get(target) || [];
                    if (sites.length > 0) {
                      setTimelineDrilldownCountry(target);
                    }
                  },
                },
              },
            },
          },
          series: [
            { name: 'Installation', color: '#0b8f7b', data: installData },
            { name: 'Sign-off', color: '#bfeee2', data: signoffData },
          ],
        };
      };

      const buildSiteTimelineOptions = (country, sites) => {
        if (!timelineDomain) return null;
        const categories = sites.map((site) => formatSiteLabel(site));
        const installData = [];
        const signoffData = [];

        sites.forEach((site, index) => {
          const start = parseDateValue(site.startDate);
          const installEnd = parseDateValue(site.installEndDate || site.endDate);
          const end = parseDateValue(site.endDate);
          if (!start || !end) return;
          const installEndDate = installEnd && installEnd > start ? installEnd : end;
          installData.push({ x: start.getTime(), x2: installEndDate.getTime(), y: index, site });
          if (end.getTime() > installEndDate.getTime()) {
            signoffData.push({ x: installEndDate.getTime(), x2: end.getTime(), y: index, site });
          }
        });

        return {
          chart: {
            type: 'xrange',
            height: Math.max(260, categories.length * 26 + 90),
            spacingLeft: 10,
            spacingRight: 10,
          },
          accessibility: { enabled: false },
          title: { text: null },
          credits: { enabled: false },
          legend: { enabled: false },
          xAxis: {
            type: 'datetime',
            min: timelineDomain.min,
            max: timelineDomain.max,
            plotLines: timelineDomain.now !== null
              ? [{ color: '#1f3b64', width: 2, value: timelineDomain.now, zIndex: 5 }]
              : [],
          },
          yAxis: {
            categories,
            reversed: true,
            title: { text: null },
          },
          tooltip: {
            formatter() {
              const label = categories[this.point.y] || formatSiteLabel(this.point.site) || 'Site';
              const startLabel = formatYmd(new Date(this.point.x));
              const endLabel = formatYmd(new Date(this.point.x2));
              return `<b>${label}</b><br/>${this.series.name}: ${startLabel} → ${endLabel}`;
            },
          },
          plotOptions: {
            series: {
              borderRadius: 4,
              pointPadding: 0.2,
              groupPadding: 0.1,
              colorByPoint: false,
            },
          },
          series: [
            { name: 'Installation', color: '#0b8f7b', data: installData },
            { name: 'Sign-off', color: '#bfeee2', data: signoffData },
          ],
        };
      };

      const countryTimelineOptions = buildCountryTimelineOptions();
      const activeDrilldownCountry = timelineDrilldownCountry || null;
      const activeSites = activeDrilldownCountry ? (timelineSiteMap.get(activeDrilldownCountry) || []) : [];
      const siteTimelineOptions = activeDrilldownCountry && activeSites.length > 0
        ? buildSiteTimelineOptions(activeDrilldownCountry, activeSites)
        : null;

      return (
        <div className="d-flex flex-column gap-3">
          {!timelineItems.length && (
            <div className="text-muted small">No timeline data available.</div>
          )}
          {timelineItems.length > 0 && !timelineDomain && (
            <div className="text-muted small">Timeline dates are missing.</div>
          )}
          {timelineItems.length > 0 && timelineDomain && (
            <>
              {activeDrilldownCountry ? (
                <div className="d-flex flex-column gap-2">
                  <div className="d-flex align-items-center justify-content-between">
                    <div className="fw-semibold">
                      <CountryAnchor country={activeDrilldownCountry} />
                      <span className="badge text-bg-light ms-2">{activeSites.length}</span>
                    </div>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={clearTimelineDrilldown}>
                      Back to countries
                    </button>
                  </div>
                  {siteTimelineOptions ? (
                    <HighchartsReact highcharts={Highcharts} options={siteTimelineOptions} />
                  ) : (
                    <div className="text-muted small">No site timeline data available.</div>
                  )}
                </div>
              ) : (
                countryTimelineOptions && (
                  <HighchartsReact highcharts={Highcharts} options={countryTimelineOptions} />
                )
              )}
            </>
          )}
        </div>
      );
    }

    if (meta.key === '__trend_green' || meta.key === '__trend_amber' || meta.key === '__trend_red') {
      return (
        <div className="d-flex flex-column gap-2">
          {trendLoading && (
            <div className="text-muted small">Loading trend overrides...</div>
          )}
          {trendError && (
            <div className="alert alert-warning py-2 mb-0" role="alert">
              {trendError}
            </div>
          )}
          {!trendLoading && !trendError && presentationEditMode && meta.key === '__trend_green' && (
            <div className="table-responsive">
              <table className="table table-sm table-bordered table-striped align-middle mb-0">
                <colgroup>
                  <col style={{ width: '30%' }} />
                  <col style={{ width: '15%' }} />
                  <col style={{ width: '55%' }} />
                </colgroup>
                <thead className="table-light">
                  <tr>
                    <th>Country</th>
                    <th>RAG</th>
                    <th>Comment</th>
                  </tr>
                </thead>
                <tbody>
                  {trendItems.map((row) => {
                    const draft = getTrendDraft(row.country);
                    const ragValue = draft.rag !== null && draft.rag !== undefined ? draft.rag : (row.rag ?? '');
                    const commentValue = draft.comment !== null && draft.comment !== undefined ? draft.comment : (row.comment ?? '');
                    return (
                      <tr key={row.country}>
                        <td className="fw-semibold">
                          <CountryAnchor country={row.country} />
                        </td>
                        <td>
                          <select
                            className="form-select form-select-sm"
                            value={ragValue}
                            onChange={(event) => updateTrendDraft(row.country, { rag: event.target.value })}
                          >
                            <option value="">—</option>
                            <option value="Green">Green</option>
                            <option value="Amber">Amber</option>
                            <option value="Red">Red</option>
                          </select>
                        </td>
                        <td>
                          <input
                            type="text"
                            className="form-control form-control-sm"
                            value={commentValue ?? ''}
                            onChange={(event) => updateTrendDraft(row.country, { comment: event.target.value })}
                          />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
          {!trendLoading && !trendError && presentationEditMode && meta.key !== '__trend_green' && (
            <div className="text-muted small">Edit RAG & comments in Country Trend: Green.</div>
          )}
          {!trendLoading && !trendError && !presentationEditMode && (
            (() => {
              const group = meta.key === '__trend_green'
                ? trendGroups.green
                : meta.key === '__trend_amber'
                  ? trendGroups.amber
                  : trendGroups.red;
              if (group.length === 0) {
                return (
                  <div className="text-muted small">
                    {meta.key === '__trend_green'
                      ? 'No green countries available.'
                      : meta.key === '__trend_amber'
                        ? 'No amber countries available.'
                        : 'No red countries available.'}
                  </div>
                );
              }
              return (
                <div className="d-flex flex-column flex-lg-row gap-3 align-items-stretch">
                  <div className="table-responsive flex-grow-1">
                    <table className="table table-sm table-bordered table-striped align-middle mb-0">
                      <colgroup>
                        <col style={{ width: '24%' }} />
                        <col style={{ width: '10%' }} />
                        <col style={{ width: '14%' }} />
                        <col style={{ width: '16%' }} />
                        <col style={{ width: '12%' }} />
                        <col style={{ width: '24%' }} />
                      </colgroup>
                      <thead className="table-light">
                        <tr>
                          <th>Country</th>
                          <th>Total</th>
                          <th>Assessments</th>
                          <th>Ongoing Installation</th>
                          <th>Stores Installed</th>
                          <th>Comment</th>
                        </tr>
                      </thead>
                      <tbody>
                        {group.map((row) => (
                          <tr key={row.country}>
                            <td className="fw-semibold">
                              <CountryAnchor country={row.country} />
                            </td>
                            <td>{formatDisplayValue(row.stores)}</td>
                            <td>{formatDisplayValue(row.assessed)}</td>
                            <td>{formatDisplayValue(row.ongoingInstallations)}</td>
                            <td>{formatDisplayValue(row.storesInstalled)}</td>
                            <td className="text-muted small">{formatDisplayValue(row.comment)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  <div className="trend-traffic-light" data-variant={meta.key}>
                    <img
                      src={meta.key === '__trend_green'
                        ? '/images/green.png'
                        : meta.key === '__trend_amber'
                          ? '/images/yellow.png'
                          : '/images/red.png'}
                      alt={meta.key === '__trend_green'
                        ? 'Green traffic light'
                        : meta.key === '__trend_amber'
                          ? 'Amber traffic light'
                          : 'Red traffic light'}
                      className="trend-traffic-light__image"
                    />
                  </div>
                </div>
              );
            })()
          )}
        </div>
      );
    }

    if (meta.key === '__issues') {
      const ikeaIssues = filterGeneralIssues(Array.isArray(generalIssues.ikea) ? generalIssues.ikea : []);
      const hpeIssues = filterGeneralIssues(Array.isArray(generalIssues.hpe) ? generalIssues.hpe : []);
      return (
        <div className="d-flex flex-column gap-4">
          {renderGeneralIssuesTable(ikeaIssues, 'Issues - IKEA')}
          {renderGeneralIssuesTable(hpeIssues, 'Issues - HPE')}
        </div>
      );
    }

    return <div className="text-muted">{meta.body}</div>;
  };

  const exportPresentation = async () => {
    setPresentationExporting(true);
    try {
      const response = await fetch('/api/smartsheet/presentation/export', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ countries: presentationCountryFilter }),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
      const blob = await response.blob();
      const disposition = response.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename="?([^";]+)"?/i);
      const filename = match?.[1] || 'presentation-export.pptx';
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      alert(error.message || 'Failed to export presentation.');
    } finally {
      setPresentationExporting(false);
    }
  };

  const exportPresentationHtml = async () => {
    setPresentationHtmlExporting(true);
    try {
      const response = await fetch('/api/smartsheet/presentation/export-html', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ countries: presentationCountryFilter }),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
      const blob = await response.blob();
      const disposition = response.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename="?([^";]+)"?/i);
      const filename = match?.[1] || 'presentation-export.html';
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      alert(error.message || 'Failed to export offline HTML.');
    } finally {
      setPresentationHtmlExporting(false);
    }
  };

  const exportPresentationXlsx = async () => {
    setPresentationXlsxExporting(true);
    try {
      const response = await fetch('/api/smartsheet/presentation/export-xlsx', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ countries: presentationCountryFilter }),
      });
      if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload?.message || `HTTP ${response.status}`);
      }
      const blob = await response.blob();
      const disposition = response.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename="?([^";]+)"?/i);
      const filename = match?.[1] || 'presentation-export.xlsx';
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      alert(error.message || 'Failed to export XLSX.');
    } finally {
      setPresentationXlsxExporting(false);
    }
  };

  useEffect(() => {
    if (activeTab === 'status' && !statusLoaded && !statusLoading) {
      fetchStatus();
    }
  }, [activeTab, fetchStatus, statusLoaded, statusLoading]);

  useEffect(() => {
    if (activeTab === 'reports' && !reportLoading && reportList.length === 0) {
      fetchReports();
    }
  }, [activeTab, fetchReports, reportList.length, reportLoading]);

  useEffect(() => {
    if (activeTab === 'reports' && selectedReportId) {
      fetchReportMeta(selectedReportId);
    }
  }, [activeTab, selectedReportId, fetchReportMeta]);

  useEffect(() => {
    if (activeTab === 'task-tracker' && !taskTrackerLoaded && !taskTrackerLoading) {
      fetchTaskTracker();
    }
  }, [activeTab, fetchTaskTracker, taskTrackerLoaded, taskTrackerLoading]);

  return (
    <div className="smartsheet-pivot">
      <ul className="nav nav-tabs mb-3" role="tablist">
        <li className="nav-item" role="presentation">
          <button
            type="button"
            className={`nav-link ${activeTab === 'explorer' ? 'active' : ''}`}
            role="tab"
            aria-selected={activeTab === 'explorer'}
            onClick={() => setActiveTab('explorer')}
          >
            Explorer
          </button>
        </li>
        <li className="nav-item" role="presentation">
          <button
            type="button"
            className={`nav-link ${activeTab === 'analyse' ? 'active' : ''}`}
            role="tab"
            aria-selected={activeTab === 'analyse'}
            onClick={() => setActiveTab('analyse')}
          >
            Analyse
          </button>
        </li>
        <li className="nav-item" role="presentation">
          <button
            type="button"
            className={`nav-link ${activeTab === 'presentation' ? 'active' : ''}`}
            role="tab"
            aria-selected={activeTab === 'presentation'}
            onClick={() => setActiveTab('presentation')}
          >
            Presentation
          </button>
        </li>
        <li className="nav-item" role="presentation">
          <button
            type="button"
            className={`nav-link ${activeTab === 'status' ? 'active' : ''}`}
            role="tab"
            aria-selected={activeTab === 'status'}
            onClick={() => setActiveTab('status')}
          >
            Status
          </button>
        </li>
        <li className="nav-item" role="presentation">
          <button
            type="button"
            className={`nav-link ${activeTab === 'reports' ? 'active' : ''}`}
            role="tab"
            aria-selected={activeTab === 'reports'}
            onClick={() => setActiveTab('reports')}
          >
            Reports
          </button>
        </li>
        <li className="nav-item" role="presentation">
          <button
            type="button"
            className={`nav-link ${activeTab === 'task-tracker' ? 'active' : ''}`}
            role="tab"
            aria-selected={activeTab === 'task-tracker'}
            onClick={() => setActiveTab('task-tracker')}
          >
            Task Tracker
          </button>
        </li>
      </ul>

      {activeTab === 'explorer' && (
        <>
          <div className="card shadow-sm mb-3">
            <div className="card-body">
              <div className="row g-3 align-items-end">
                <div className="col-12 col-md-4">
                  <label className="form-label fw-medium" htmlFor="smartsheet-workspace">Workspace</label>
                  <select
                    id="smartsheet-workspace"
                    className="form-select"
                    value={selectedWorkspace}
                    onChange={onWorkspaceChange}
                    disabled={workspaceLoading}
                  >
                    <option value="">Select a workspace…</option>
                    {workspaces.map((workspace) => (
                      <option key={workspace.id ?? workspace.name} value={workspace.id ?? ''}>
                        {workspace.name ?? workspace.id}
                      </option>
                    ))}
                  </select>
                  {workspaceLoading && <div className="form-text text-muted">Loading workspaces…</div>}
                  {workspaceError && <div className="form-text text-danger">{workspaceError}</div>}
                </div>

                <div className="col-12 col-md-4">
                  <label className="form-label fw-medium" htmlFor="smartsheet-sheet">Sheet</label>
                  <select
                    id="smartsheet-sheet"
                    className="form-select"
                    value={selectedSheet}
                    onChange={onSheetChange}
                    disabled={!selectedWorkspace || sheetsLoading}
                  >
                    <option value="">Select a sheet…</option>
                    {sheets.map((sheet) => (
                      <option key={sheet.id ?? sheet.name} value={sheet.id ?? ''}>
                        {sheet.name ?? sheet.id}
                      </option>
                    ))}
                  </select>
                  {sheetsLoading && <div className="form-text text-muted">Loading sheets…</div>}
                  {sheetsError && <div className="form-text text-danger">{sheetsError}</div>}
                </div>

                <div className="col-12 col-md-4 d-flex gap-2">
                  <button
                    type="button"
                    className="btn btn-outline-secondary"
                    disabled={workspaceLoading}
                    onClick={fetchWorkspaces}
                  >
                    Reload Workspaces
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    disabled={!selectedSheet || pivotLoading}
                    onClick={onRefresh}
                  >
                    Refresh Sheet
                  </button>
                </div>
              </div>
              <div className="mt-3 small text-muted">
                {currentWorkspace && (
                  <span className="me-3">Workspace: <strong>{currentWorkspace.name ?? currentWorkspace.id}</strong></span>
                )}
                {currentSheet && (
                  <span>Sheet: <strong>{currentSheet.name ?? currentSheet.id}</strong></span>
                )}
              </div>
            </div>
          </div>

          <div className="card shadow-sm">
            <div className="card-body">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h2 className="h5 mb-0">Sheet Data</h2>
                  <small className="text-muted">
                    {pivotLoading && 'Loading Smartsheet data…'}
                    {!pivotLoading && selectedSheet && lastUpdated && `Last updated ${lastUpdated.toLocaleString()}`}
                    {!pivotLoading && !selectedSheet && 'Select a sheet to load details.'}
                  </small>
                </div>
                {rows.length > 0 && (
                  <span className="badge bg-secondary">{rows.length.toLocaleString()} rows</span>
                )}
              </div>

              {pivotError && (
                <div className="alert alert-danger" role="alert">
                  {pivotError}
                </div>
              )}

              {!columns.length && !pivotLoading && (
                <div className="alert alert-info" role="alert">
                  Columns will appear once a sheet is loaded.
                </div>
              )}

              <div className="table-responsive">
                <table ref={tableRef} className="table table-striped table-bordered w-100" data-testid="smartsheet-pivot-table">
                  <thead>
                    <tr>
                      {columns.map((col) => (
                        <th key={col.key}>{col.label}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </>
      )}

      {activeTab === 'analyse' && (
        <>
          <div className="card shadow-sm mb-3">
            <div className="card-body">
              <h2 className="h5 mb-3">Task Duration Analysis</h2>
              <div className="row g-3 align-items-end">
                <div className="col-12 col-lg-4">
                  <label className="form-label fw-medium" htmlFor="smartsheet-task-a">Task A</label>
                  <select
                    id="smartsheet-task-a"
                    className="form-select"
                    value={taskA}
                    onChange={(event) => setTaskA(event.target.value)}
                    disabled={analyseTaskLoading}
                  >
                    <option value="">Select a task…</option>
                    {taskOptions.map((option) => (
                      <option key={`task-a-${option.value}`} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>
                <div className="col-12 col-lg-2">
                  <label className="form-label fw-medium" htmlFor="smartsheet-field-a">Compare</label>
                  <select
                    id="smartsheet-field-a"
                    className="form-select"
                    value={fieldA}
                    onChange={(event) => setFieldA(event.target.value)}
                  >
                    {fieldOptions.map((option) => (
                      <option key={`field-a-${option.value}`} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>

                <div className="col-12 col-lg-4">
                  <label className="form-label fw-medium" htmlFor="smartsheet-task-b">Task B</label>
                  <select
                    id="smartsheet-task-b"
                    className="form-select"
                    value={taskB}
                    onChange={(event) => setTaskB(event.target.value)}
                    disabled={analyseTaskLoading}
                  >
                    <option value="">Select a task…</option>
                    {taskOptions.map((option) => (
                      <option key={`task-b-${option.value}`} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>
                <div className="col-12 col-lg-2">
                  <label className="form-label fw-medium" htmlFor="smartsheet-field-b">Compare</label>
                  <select
                    id="smartsheet-field-b"
                    className="form-select"
                    value={fieldB}
                    onChange={(event) => setFieldB(event.target.value)}
                  >
                    {fieldOptions.map((option) => (
                      <option key={`field-b-${option.value}`} value={option.value}>{option.label}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <button
                  type="button"
                  className="btn btn-primary"
                  onClick={onRunAnalysis}
                  disabled={!taskA || !taskB || analyseLoading}
                >
                  Run Analysis
                </button>
                {analyseTaskLoading && <span className="text-muted small">Loading tasks…</span>}
                {analyseTaskError && <span className="text-danger small">{analyseTaskError}</span>}
              </div>
            </div>
          </div>

          <div className="card shadow-sm">
            <div className="card-body">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h2 className="h6 mb-0">Results</h2>
                  <small className="text-muted">
                    {analyseLoading && 'Running analysis…'}
                    {!analyseLoading && analyseRows.length === 0 && 'Run analysis to load results.'}
                  </small>
                </div>
                {analyseRows.length > 0 && (
                  <span className="badge bg-secondary">{analyseRows.length.toLocaleString()} rows</span>
                )}
              </div>

              {analyseError && (
                <div className="alert alert-danger" role="alert">
                  {analyseError}
                </div>
              )}

              {!analyseLoading && analyseRows.length === 0 && !analyseError && (
                <div className="alert alert-info" role="alert">
                  No results yet. Pick two tasks and run analysis.
                </div>
              )}

              {analyseRows.length > 0 && (
                <div className="table-responsive">
                  <table ref={analyseTableRef} className="table table-striped table-bordered w-100" data-testid="smartsheet-analyse-table">
                    <thead>
                      <tr>
                        {analyseColumns.map((col) => (
                          <th key={col.key}>{col.label}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </>
      )}

      {activeTab === 'presentation' && (
        <div className="d-flex flex-column gap-3">
          <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
              <h2 className="h5 mb-0">IKEA/Wi-Fi migration – HPE Programme meeting</h2>
              <div className="text-muted small">
                {presentationLoading && 'Loading presentation data…'}
                {!presentationLoading && presentationMeta && (
                  <span>{presentationDateLabel}</span>
                )}
                {!presentationLoading && !presentationMeta && 'Current and next month coverage.'}
              </div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <select
                className="form-select form-select-sm"
                multiple
                value={presentationCountryFilter}
                onChange={onPresentationCountryChange}
                style={{ minWidth: 220, maxHeight: 120 }}
              >
                <optgroup label="Exec Summary">
                  <option value="__exec_highlights">Highlights</option>
                  <option value="__exec_overview">Programme Overview Per Country</option>
                  <option value="__exec_status">Status planned assessments and installations</option>
                  <option value="__exec_timeline">Timeline</option>
                </optgroup>
                <optgroup label="Country Trend">
                  <option value="__trend_green">Green</option>
                  <option value="__trend_amber">Amber</option>
                  <option value="__trend_red">Red</option>
                </optgroup>
                <optgroup label="General Issues">
                  <option value="__issues">General Issues</option>
                </optgroup>
                <optgroup label="Country Detail">
                  {presentationCountries.map((country) => (
                    <option key={country} value={country}>{country}</option>
                  ))}
                </optgroup>
              </select>
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm"
                onClick={() => setPresentationCountryFilter([])}
                disabled={!presentationCountryFilter.length}
              >
                Clear
              </button>
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm"
                onClick={fetchPresentation}
                disabled={presentationLoading}
              >
                Refresh
              </button>
              <button
                type="button"
                className="btn btn-outline-primary btn-sm"
                onClick={() => setPresentationEditMode((prev) => !prev)}
                disabled={presentationLoading}
              >
                {presentationEditMode ? 'Done' : 'Edit'}
              </button>
              <button
                type="button"
                className="btn btn-outline-dark btn-sm"
                onClick={openSlideshow}
                disabled={presentationLoading || filteredPresentationItems.length === 0}
              >
                Slideshow
              </button>
              {presentationEditMode && (
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  onClick={savePresentationEdits}
                  disabled={presentationSaving}
                >
                  {presentationSaving ? 'Saving…' : 'Save'}
                </button>
              )}
              <button
                type="button"
                className="btn btn-primary btn-sm"
                onClick={exportPresentation}
                disabled={presentationExporting}
              >
                {presentationExporting ? 'Exporting…' : 'Export PPTX'}
              </button>
              <button
                type="button"
                className="btn btn-outline-primary btn-sm"
                onClick={exportPresentationXlsx}
                disabled={presentationXlsxExporting}
              >
                {presentationXlsxExporting ? 'Exporting…' : 'Export XLSX'}
              </button>
              <button
                type="button"
                className="btn btn-outline-primary btn-sm"
                onClick={exportPresentationHtml}
                disabled={presentationHtmlExporting}
              >
                {presentationHtmlExporting ? 'Exporting…' : 'Export Offline HTML'}
              </button>
            </div>
          </div>

          {presentationError && (
            <div className="alert alert-danger" role="alert">
              {presentationError}
            </div>
          )}

          {presentationSaveError && (
            <div className="alert alert-warning" role="alert">
              {presentationSaveError}
            </div>
          )}

          {!presentationLoading && filteredPresentationItems.length === 0 && (
            <div className="alert alert-info" role="alert">
              No planned assessments, installations, or post-deployment sign-off found for the selected months.
            </div>
          )}

          <div className="d-flex flex-column gap-3">
            {execCardMeta.map((meta) => (
              <div
                key={meta.key}
                className="card shadow-sm"
                id={meta.key === '__exec_overview' ? 'exec-overview' : undefined}
                ref={meta.key === '__exec_overview' ? execOverviewRef : undefined}
              >
                <div className="card-header fw-semibold position-relative">
                  {meta.title}
                  <span
                    className="text-muted small"
                    style={{ position: 'absolute', right: 16, top: '50%', transform: 'translateY(-50%)' }}
                  >
                    {presentationDateLabel}
                  </span>
                </div>
                <div className="card-body">
                  {renderExecCardBody(meta)}
                </div>
              </div>
            ))}
          </div>

          {filteredPresentationItems.map((countryBlock) => (
              <div key={countryBlock.country} className="card shadow-sm" id={countryAnchorId(countryBlock.country)}>
                <div
                  className="card-header country-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 position-relative"
                  style={{ height: 180 }}
                >
                <strong style={{ position: 'relative', zIndex: 2 }}>
                  <a
                    href="#exec-overview"
                    className="text-decoration-none text-reset"
                    onClick={(event) => {
                      event.preventDefault();
                      scrollToExecOverview();
                    }}
                  >
                    <CountryFlag country={countryBlock.country} />
                    {countryBlock.country}
                  </a>
                  {renderCountryMedals(countryBlock.country)}
                </strong>
                {(() => {
                  const progress = findProgressForCountry(countryBlock.country);
                  if (!progress || !Array.isArray(progress.tasks) || progress.tasks.length === 0) {
                    return null;
                  }
                  return (
                    <>
                      <div className="d-flex flex-column gap-2 d-lg-none mx-auto" style={{ maxWidth: 520 }}>
                        {progress.tasks.map(renderProgressRow)}
                      </div>
                      <div
                        className="d-none d-lg-flex position-absolute justify-content-center"
                        style={{ left: 0, right: 0, top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }}
                      >
                        <div className="d-flex flex-column gap-2" style={{ maxWidth: 520 }}>
                          {progress.tasks.map(renderProgressRow)}
                        </div>
                      </div>
                    </>
                  );
                })()}
                <span
                  className="text-muted small"
                  style={{ position: 'absolute', right: 16, top: '50%', transform: 'translateY(-50%)' }}
                >
                  {presentationDateLabel}
                </span>
              </div>
              <div className="card-body">
                <div className="d-flex flex-column gap-4">
                  <div>
                    <div className="fw-semibold mb-2">Planned Assessments</div>
                    <div className="row g-3">
                      <div className="col-12 col-lg-6">
                        <div className="text-uppercase text-muted small mb-2">
                          {assessmentMeta?.currentMonth ?? presentationMeta?.currentMonth ?? 'Current month'}
                        </div>
                        {renderAssessmentTable(countryBlock.assessments?.current || [], 'assessments-current', countryBlock.country)}
                      </div>
                      <div className="col-12 col-lg-6">
                        <div className="text-uppercase text-muted small mb-2">
                          {assessmentMeta?.nextMonth ?? presentationMeta?.nextMonth ?? 'Next month'}
                        </div>
                        {renderAssessmentTable(countryBlock.assessments?.next || [], 'assessments-next', countryBlock.country)}
                      </div>
                    </div>
                  </div>

                  <div>
                    <div className="fw-semibold mb-2">Planned Installations</div>
                    <div className="row g-3">
                      <div className="col-12 col-lg-6">
                        <div className="text-uppercase text-muted small mb-2">
                          {installationMeta?.currentMonth ?? presentationMeta?.currentMonth ?? 'Current month'}
                        </div>
                        {renderAssessmentTable(countryBlock.installations?.current || [], 'installations-current', countryBlock.country)}
                      </div>
                      <div className="col-12 col-lg-6">
                        <div className="text-uppercase text-muted small mb-2">
                          {installationMeta?.nextMonth ?? presentationMeta?.nextMonth ?? 'Next month'}
                        </div>
                        {renderAssessmentTable(countryBlock.installations?.next || [], 'installations-next', countryBlock.country)}
                      </div>
                    </div>
                  </div>

                  <div>
                    <div className="fw-semibold mb-2">Post-Deployment &amp; Sign-off</div>
                    <div className="row g-3">
                      <div className="col-12 col-lg-6">
                        <div className="text-uppercase text-muted small mb-2">
                          {postDeploymentMeta?.currentMonth ?? presentationMeta?.currentMonth ?? 'Current month'}
                        </div>
                        {renderAssessmentTable(countryBlock.postDeployment?.current || [], 'postdeployment-current', countryBlock.country)}
                      </div>
                      <div className="col-12 col-lg-6">
                        <div className="text-uppercase text-muted small mb-2">
                          {postDeploymentMeta?.nextMonth ?? presentationMeta?.nextMonth ?? 'Next month'}
                        </div>
                        {renderAssessmentTable(countryBlock.postDeployment?.next || [], 'postdeployment-next', countryBlock.country)}
                      </div>
                    </div>
                  </div>

                  <div>
                    <div className="fw-semibold mb-2">Issue Log</div>
                    {presentationEditMode && (
                      <div className="border rounded p-2 mb-3">
                        <div className="row g-2 align-items-end">
                          <div className="col-12 col-lg-3">
                            <label className="form-label small mb-1">Site</label>
                            <select
                              className="form-select form-select-sm"
                              value={getIssueDraft(countryBlock.country).storeId}
                              onChange={(event) => {
                                const selected = buildSiteOptions(countryBlock).find((opt) => opt.siteId === event.target.value);
                                updateIssueDraft(countryBlock.country, {
                                  storeId: event.target.value,
                                  storeName: selected?.siteName || getIssueDraft(countryBlock.country).storeName,
                                });
                              }}
                            >
                              <option value="">Select site</option>
                              {buildSiteOptions(countryBlock).map((site) => (
                                <option key={site.siteId || site.siteName} value={site.siteId}>
                                  {site.siteName || site.siteId}{site.siteId && site.siteName ? ` (${site.siteId})` : ''}
                                </option>
                              ))}
                            </select>
                          </div>
                          <div className="col-12 col-lg-3">
                            <label className="form-label small mb-1">Site Name</label>
                            <input
                              type="text"
                              className="form-control form-control-sm"
                              value={getIssueDraft(countryBlock.country).storeName}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { storeName: event.target.value })}
                              placeholder="Site Name"
                            />
                          </div>
                          <div className="col-12 col-lg-2">
                            <label className="form-label small mb-1">Site ID</label>
                            <input
                              type="text"
                              className="form-control form-control-sm"
                              value={getIssueDraft(countryBlock.country).storeId}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { storeId: event.target.value })}
                              placeholder="Site ID"
                            />
                          </div>
                          <div className="col-12 col-lg-2">
                            <label className="form-label small mb-1">Priority</label>
                            <select
                              className="form-select form-select-sm"
                              value={getIssueDraft(countryBlock.country).priority}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { priority: event.target.value })}
                            >
                              <option value="">—</option>
                              <option value="Critical">Critical</option>
                              <option value="High">High</option>
                              <option value="Medium">Medium</option>
                              <option value="Low">Low</option>
                            </select>
                          </div>
                          <div className="col-12 col-lg-2">
                            <label className="form-label small mb-1">Resolve Date</label>
                            <input
                              type="date"
                              className="form-control form-control-sm"
                              value={getIssueDraft(countryBlock.country).resolveDate}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { resolveDate: event.target.value })}
                            />
                          </div>
                          <div className="col-12">
                            <label className="form-label small mb-1">Description</label>
                            <textarea
                              className="form-control form-control-sm"
                              rows={2}
                              value={getIssueDraft(countryBlock.country).description}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { description: event.target.value })}
                            />
                          </div>
                          <div className="col-12 col-lg-6">
                            <label className="form-label small mb-1">Responsible Party</label>
                            <input
                              type="text"
                              className="form-control form-control-sm"
                              value={getIssueDraft(countryBlock.country).responsibleParty}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { responsibleParty: event.target.value })}
                            />
                          </div>
                          <div className="col-12 col-lg-6">
                            <label className="form-label small mb-1">Action to be taken (DD.MM.YY - NS)</label>
                            <input
                              type="text"
                              className="form-control form-control-sm"
                              value={getIssueDraft(countryBlock.country).actionRequired}
                              onChange={(event) => updateIssueDraft(countryBlock.country, { actionRequired: event.target.value })}
                            />
                          </div>
                          <div className="col-12 d-flex justify-content-end">
                            <button
                              type="button"
                              className="btn btn-sm btn-primary"
                              onClick={() => submitIssue(countryBlock.country)}
                              disabled={issueSaving[countryBlock.country]}
                            >
                              {issueSaving[countryBlock.country]
                                ? 'Saving…'
                                : (getIssueEditTarget(countryBlock.country) ? 'Save' : '+ Add Issue')}
                            </button>
                          </div>
                        </div>
                      </div>
                    )}
                    {renderIssueTable(countryBlock.issues?.issues || [], countryBlock.country)}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {activeTab === 'presentation' && slideshowOpen && (
        <div
          className="position-fixed top-0 start-0 w-100 h-100 d-flex flex-column"
          style={{ background: 'rgba(0,0,0,0.8)', zIndex: 2000 }}
        >
          <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-3 text-white">
            <div className="fw-semibold">
              {slideshowItem?.title || 'Card'}
              {slideshowItem?.type === 'country' ? renderCountryMedals(slideshowItem.countryBlock?.country) : null}
            </div>
            {(() => {
              if (!slideshowItem || slideshowItem.type !== 'country') return null;
              const progress = findProgressForCountry(slideshowItem.countryBlock?.country);
              if (!progress || !Array.isArray(progress.tasks) || progress.tasks.length === 0) {
                return null;
              }
              return (
                <div className="d-flex flex-column gap-2 mx-auto" style={{ minWidth: 320 }}>
                  {progress.tasks.map(renderProgressRow)}
                </div>
              );
            })()}
            <button
              type="button"
              className="btn btn-sm btn-outline-light"
              onClick={closeSlideshow}
            >
              Close
            </button>
          </div>
          <div className="flex-grow-1 overflow-auto p-3">
            {slideshowItem ? (
              <div className="card shadow-sm">
                <div className="card-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 position-relative">
                  <strong>
                    {slideshowItem.type === 'country'
                      ? <CountryAnchor country={slideshowItem.countryBlock?.country} />
                      : slideshowItem.title}
                  </strong>
                  <span
                    className="text-muted small"
                    style={{ position: 'absolute', right: 16, top: '50%', transform: 'translateY(-50%)' }}
                  >
                    {presentationDateLabel}
                  </span>
                </div>
                <div className="card-body">
                  {slideshowItem.type === 'exec' ? (
                    renderExecCardBody(slideshowItem.meta)
                  ) : (
                    <div className="d-flex flex-column gap-4">
                      <div>
                        <div className="fw-semibold mb-2">Planned Assessments</div>
                        <div className="row g-3">
                          <div className="col-12 col-lg-6">
                            <div className="text-uppercase text-muted small mb-2">
                              {assessmentMeta?.currentMonth ?? presentationMeta?.currentMonth ?? 'Current month'}
                            </div>
                            {renderAssessmentTable(slideshowItem.countryBlock?.assessments?.current || [], 'assessments-current', slideshowItem.countryBlock?.country)}
                          </div>
                          <div className="col-12 col-lg-6">
                            <div className="text-uppercase text-muted small mb-2">
                              {assessmentMeta?.nextMonth ?? presentationMeta?.nextMonth ?? 'Next month'}
                            </div>
                            {renderAssessmentTable(slideshowItem.countryBlock?.assessments?.next || [], 'assessments-next', slideshowItem.countryBlock?.country)}
                          </div>
                        </div>
                      </div>

                      <div>
                        <div className="fw-semibold mb-2">Planned Installations</div>
                        <div className="row g-3">
                          <div className="col-12 col-lg-6">
                            <div className="text-uppercase text-muted small mb-2">
                              {installationMeta?.currentMonth ?? presentationMeta?.currentMonth ?? 'Current month'}
                            </div>
                            {renderAssessmentTable(slideshowItem.countryBlock?.installations?.current || [], 'installations-current', slideshowItem.countryBlock?.country)}
                          </div>
                          <div className="col-12 col-lg-6">
                            <div className="text-uppercase text-muted small mb-2">
                              {installationMeta?.nextMonth ?? presentationMeta?.nextMonth ?? 'Next month'}
                            </div>
                            {renderAssessmentTable(slideshowItem.countryBlock?.installations?.next || [], 'installations-next', slideshowItem.countryBlock?.country)}
                          </div>
                        </div>
                      </div>

                      <div>
                        <div className="fw-semibold mb-2">Post-Deployment &amp; Sign-off</div>
                        <div className="row g-3">
                          <div className="col-12 col-lg-6">
                            <div className="text-uppercase text-muted small mb-2">
                              {postDeploymentMeta?.currentMonth ?? presentationMeta?.currentMonth ?? 'Current month'}
                            </div>
                            {renderAssessmentTable(slideshowItem.countryBlock?.postDeployment?.current || [], 'postdeployment-current', slideshowItem.countryBlock?.country)}
                          </div>
                          <div className="col-12 col-lg-6">
                            <div className="text-uppercase text-muted small mb-2">
                              {postDeploymentMeta?.nextMonth ?? presentationMeta?.nextMonth ?? 'Next month'}
                            </div>
                            {renderAssessmentTable(slideshowItem.countryBlock?.postDeployment?.next || [], 'postdeployment-next', slideshowItem.countryBlock?.country)}
                          </div>
                        </div>
                      </div>

                      <div>
                        <div className="fw-semibold mb-2">Issue Log</div>
                        {renderIssueTable(slideshowItem.countryBlock?.issues?.issues || [], slideshowItem.countryBlock?.country)}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ) : (
              <div className="text-white">No card data available.</div>
            )}
          </div>
          <div className="d-flex justify-content-between align-items-center p-3 bg-dark text-white">
            <button
              type="button"
              className="btn btn-outline-light"
              onClick={() => setSlideshowIndex((prev) => Math.max(0, prev - 1))}
              disabled={slideshowIndex <= 0}
            >
              Previous
            </button>
            <div className="d-flex align-items-center gap-2">
              <span className="small">Card</span>
              <select
                className="form-select form-select-sm"
                style={{ minWidth: 200 }}
                value={slideshowIndex}
                onChange={(event) => setSlideshowIndex(Number(event.target.value))}
              >
                {slideshowItems.map((item, index) => (
                  <option key={`${item.type}-${item.key}`} value={index}>{item.title}</option>
                ))}
              </select>
            </div>
            <button
              type="button"
              className="btn btn-outline-light"
              onClick={() => setSlideshowIndex((prev) => Math.min(slideshowItems.length - 1, prev + 1))}
              disabled={slideshowIndex >= slideshowItems.length - 1}
            >
              Next
            </button>
          </div>
        </div>
      )}

      {activeTab === 'status' && (
        <div className="d-flex flex-column gap-3">
          <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
              <h2 className="h5 mb-0">Status</h2>
              <div className="text-muted small">Update RAG confidence and add status log entries per site.</div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <select
                className="form-select form-select-sm"
                value={statusCountryFilter}
                onChange={(event) => setStatusCountryFilter(event.target.value)}
                style={{ minWidth: 220 }}
              >
                <option value="">All countries</option>
                {statusCountries.map((country) => (
                  <option key={country} value={country}>{country}</option>
                ))}
              </select>
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm"
                onClick={fetchStatus}
                disabled={statusLoading}
              >
                Refresh
              </button>
            </div>
          </div>

          {statusLoading && <div className="text-muted">Loading status data…</div>}

          {!statusLoading && statusItems.length === 0 && (
            <div className="alert alert-info" role="alert">
              No sites available for status updates.
            </div>
          )}

          {!statusLoading && statusItems.length > 0 && (
            <div className="d-flex flex-column gap-3">
              {statusItems
                .filter((site) => !statusCountryFilter || site.country === statusCountryFilter)
                .reduce((acc, site) => {
                const group = acc.find((item) => item.country === site.country);
                if (group) {
                  group.sites.push(site);
                } else {
                  acc.push({ country: site.country, sites: [site] });
                }
                return acc;
              }, [])
                .map((countryBlock) => (
                <div key={countryBlock.country} className="card shadow-sm">
                  <div className="card-header position-relative">
                    <strong>{countryBlock.country}</strong>
                    <span
                      className="text-muted small"
                      style={{ position: 'absolute', right: 16, top: '50%', transform: 'translateY(-50%)' }}
                    >
                      {presentationDateLabel}
                    </span>
                  </div>
                  <div className="card-body d-flex flex-column gap-3">
                    {countryBlock.sites.map((site, siteIndex) => (
                      <div key={`${site.country}-${site.siteId}-${siteIndex}`} className="border rounded p-3">
                        <div className="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-2">
                          <div className="fw-semibold">{site.siteName || 'Site'} {site.siteId ? `(${site.siteId})` : ''}</div>
                        </div>
                        <div className="row g-3">
                          {statusCategories.map((category) => {
                            const categoryData = site.categories?.[category.id] || { ragConfidence: null, logs: [] };
                            const draft = getStatusDraft(site.country, site.siteId, category.id);
                            return (
                              <div key={`${site.siteId}-${category.id}`} className="col-12 col-lg-4">
                                <div className="border rounded h-100 p-2 d-flex flex-column gap-2">
                                  <div className="fw-semibold small">{category.label}</div>
                                  <div>
                                    <label className="form-label small mb-1">RAG Confidence</label>
                                    <select
                                      className="form-select form-select-sm"
                                      value={draft.ragConfidence ?? categoryData.ragConfidence ?? ''}
                                      onChange={(event) =>
                                        updateStatusDraft(site.country, site.siteId, category.id, { ragConfidence: event.target.value })
                                      }
                                    >
                                      <option value="">—</option>
                                      <option value="High">High</option>
                                      <option value="Medium">Medium</option>
                                      <option value="Low">Low</option>
                                    </select>
                                  </div>
                                  <div>
                                    <label className="form-label small mb-1">Add status update</label>
                                    <textarea
                                      className="form-control form-control-sm"
                                      rows={2}
                                      value={draft.statusText || ''}
                                      onChange={(event) =>
                                        updateStatusDraft(site.country, site.siteId, category.id, { statusText: event.target.value })
                                      }
                                    />
                                  </div>
                                  {draft.error && <div className="text-danger small">{draft.error}</div>}
                                  <button
                                    type="button"
                                    className="btn btn-sm btn-primary"
                                    disabled={draft.saving}
                                    onClick={() => submitStatusLog(site, category.id)}
                                  >
                                    {draft.saving ? 'Saving…' : 'Add log entry'}
                                  </button>
                                  <div className="mt-2">
                                    <div className="text-uppercase text-muted small mb-1">Log</div>
                                    {categoryData.logs && categoryData.logs.length > 0 ? (
                                      <ul className="list-unstyled small mb-0">
                                        {categoryData.logs.map((entry) => (
                                          <li key={entry.id ?? `${entry.createdAt}-${entry.statusText}`} className="mb-2">
                                            <div className="d-flex justify-content-between">
                                              <span>{formatDateTimeDisplay(entry.createdAt)}</span>
                                              {entry.ragConfidence && (
                                                <span className={`badge bg-${confidenceVariant(entry.ragConfidence)}`}>{entry.ragConfidence}</span>
                                              )}
                                            </div>
                                            <div>{entry.statusText}</div>
                                          </li>
                                        ))}
                                      </ul>
                                    ) : (
                                      <div className="text-muted small">No status updates yet.</div>
                                    )}
                                  </div>
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {activeTab === 'task-tracker' && (
        <div className="d-flex flex-column gap-3">
          <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
              <h2 className="h5 mb-0">Task Tracker</h2>
              <div className="text-muted small">Log tracking entries and link them to tasks.</div>
            </div>
            <button
              type="button"
              className="btn btn-outline-secondary btn-sm"
              onClick={fetchTaskTracker}
              disabled={taskTrackerLoading}
            >
              Refresh
            </button>
          </div>

          {taskTrackerError && (
            <div className="alert alert-warning" role="alert">
              {taskTrackerError}
            </div>
          )}

          <div className="card shadow-sm">
            <div className="card-body">
              <div className="row g-3">
                <div className="col-12 col-lg-2">
                  <label className="form-label small mb-1">Date</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={taskTrackerDraft.date}
                    placeholder="YYYY-MM-DD"
                    inputMode="numeric"
                    onChange={(event) => updateTaskTrackerDraft({ date: normalizeTaskTrackerDate(event.target.value) })}
                    onBlur={(event) => updateTaskTrackerDraft({ date: normalizeTaskTrackerDate(event.target.value) })}
                  />
                </div>
                <div className="col-12 col-lg-3">
                  <label className="form-label small mb-1">Category</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={taskTrackerDraft.category}
                    list="task-tracker-category-options"
                    onChange={(event) => updateTaskTrackerDraft({ category: event.target.value })}
                  />
                  <datalist id="task-tracker-category-options">
                    {taskTrackerDraft.category.trim() === '' && taskTrackerTopCategories.map((value) => (
                      <option key={`task-tracker-category-${value}`} value={value} />
                    ))}
                  </datalist>
                </div>
                <div className="col-12 col-lg-3">
                  <label className="form-label small mb-1">Responsible</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={taskTrackerDraft.responsible}
                    list="task-tracker-responsible-options"
                    onChange={(event) => updateTaskTrackerDraft({ responsible: event.target.value })}
                  />
                  <datalist id="task-tracker-responsible-options">
                    {taskTrackerDraft.responsible.trim() === '' && taskTrackerTopResponsibles.map((value) => (
                      <option key={`task-tracker-responsible-${value}`} value={value} />
                    ))}
                  </datalist>
                </div>
                <div className="col-12 col-lg-4">
                  <label className="form-label small mb-1">Assign to Tasks</label>
                  <div className="task-tracker-multiselect" ref={taskTrackerSelectRef}>
                    <button
                      type="button"
                      className="task-tracker-multiselect__toggle"
                      onClick={() =>
                        setTaskTrackerSelectOpen((prev) => {
                          const next = !prev;
                          if (next && taskTrackerOptionItems.length === 0 && !taskTrackerOptionsLoadingRef.current) {
                            fetchTaskTrackerOptions('replace');
                          }
                          return next;
                        })
                      }
                    >
                      <span>{taskTrackerDraft.tasks.length ? `${taskTrackerDraft.tasks.length} selected` : 'Select options'}</span>
                      <span className="task-tracker-multiselect__caret" />
                    </button>
                    {taskTrackerSelectOpen && (
                      <div className="task-tracker-multiselect__menu">
                        <div className="task-tracker-multiselect__filter">
                          <label className="task-tracker-multiselect__filter-label">Filter:</label>
                          <input
                            type="text"
                            className="task-tracker-multiselect__filter-input"
                            placeholder="Task Name"
                            value={taskTrackerFilterTaskName}
                            onChange={(event) => setTaskTrackerFilterTaskName(event.target.value)}
                          />
                          <div className="task-tracker-multiselect__filter-row">
                            <input
                              type="text"
                              className="task-tracker-multiselect__filter-input"
                              placeholder="Country"
                              value={taskTrackerFilterCountry}
                              onChange={(event) => setTaskTrackerFilterCountry(event.target.value)}
                            />
                            <input
                              type="text"
                              className="task-tracker-multiselect__filter-input"
                              placeholder="Site Name"
                              value={taskTrackerFilterSiteName}
                              onChange={(event) => setTaskTrackerFilterSiteName(event.target.value)}
                            />
                          </div>
                          <div className="task-tracker-multiselect__actions">
                            <button
                              type="button"
                              className="task-tracker-multiselect__action"
                              onClick={() => {
                                const existing = normalizeTaskTrackerTasks(taskTrackerDraft.tasks);
                                const existingKeys = new Set(existing.map(getTaskTrackerTaskKey));
                                const additions = taskTrackerFilteredOptions
                                  .filter((option) => !existingKeys.has(String(option.value)))
                                  .map((option) => ({
                                    taskId: option.taskId ?? null,
                                    label: option.label,
                                    country: option.country ?? null,
                                    siteName: option.siteName ?? null,
                                    taskName: option.taskName ?? null,
                                  }));
                                updateTaskTrackerDraft({ tasks: [...existing, ...additions] });
                              }}
                            >
                              Check all
                            </button>
                            <button
                              type="button"
                              className="task-tracker-multiselect__action"
                              onClick={() => {
                                const remove = new Set(taskTrackerFilteredOptions.map((option) => String(option.value)));
                                const remaining = normalizeTaskTrackerTasks(taskTrackerDraft.tasks).filter(
                                  (task) => !remove.has(getTaskTrackerTaskKey(task))
                                );
                                updateTaskTrackerDraft({ tasks: remaining });
                              }}
                            >
                              Uncheck all
                            </button>
                            <button
                              type="button"
                              className="task-tracker-multiselect__action"
                              onClick={() => {
                                setTaskTrackerFilterCountry('');
                                setTaskTrackerFilterSiteName('');
                                setTaskTrackerFilterTaskName('');
                              }}
                              disabled={!taskTrackerFilterCountry && !taskTrackerFilterSiteName && !taskTrackerFilterTaskName}
                            >
                              Clear filters
                            </button>
                          </div>
                        </div>
                        <div
                          className="task-tracker-multiselect__list"
                          onScroll={(event) => {
                            const target = event.currentTarget;
                            if (!taskTrackerOptionsHasMore || taskTrackerOptionsLoading) return;
                            if (target.scrollTop + target.clientHeight >= target.scrollHeight - 24) {
                              fetchTaskTrackerOptions('append');
                            }
                          }}
                        >
                          {taskTrackerFilteredOptions.length === 0 && (
                            <div className="task-tracker-multiselect__empty">No matching tasks</div>
                          )}
                          {taskTrackerOptionsLoading && (
                            <div className="task-tracker-multiselect__empty">Loading…</div>
                          )}
                          {taskTrackerFilteredOptions.map((option) => {
                            const optionKey = String(option.value);
                            const checked = taskTrackerSelectedKeys.has(optionKey);
                            return (
                              <label key={`tracker-task-${option.value}`} className="task-tracker-multiselect__item">
                                <input
                                  type="checkbox"
                                  checked={checked}
                                  onChange={() => {
                                    const current = normalizeTaskTrackerTasks(taskTrackerDraft.tasks);
                                    if (checked) {
                                      updateTaskTrackerDraft({
                                        tasks: current.filter((task) => getTaskTrackerTaskKey(task) !== optionKey),
                                      });
                                    } else {
                                      updateTaskTrackerDraft({
                                        tasks: [
                                          ...current,
                                          {
                                            taskId: option.taskId ?? null,
                                            label: option.label,
                                            country: option.country ?? null,
                                            siteName: option.siteName ?? null,
                                            taskName: option.taskName ?? null,
                                          },
                                        ],
                                      });
                                    }
                                  }}
                                />
                                <span>{option.label}</span>
                              </label>
                            );
                          })}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
                <div className="col-12">
                  <label className="form-label small mb-1">Description</label>
                  <textarea
                    className="form-control form-control-sm"
                    rows={3}
                    value={taskTrackerDraft.description}
                    onChange={(event) => updateTaskTrackerDraft({ description: event.target.value })}
                  />
                </div>
                <div className="col-12">
                  <label className="form-label small mb-1">Files</label>
                  {taskTrackerEditId ? (
                    <>
                      <div
                        className="task-tracker-dropzone"
                        onDragOver={(event) => event.preventDefault()}
                        onDrop={(event) => {
                          event.preventDefault();
                          uploadTaskTrackerFiles(taskTrackerEditId, event.dataTransfer.files);
                        }}
                        onClick={() => {
                          const input = document.getElementById('task-tracker-file-current');
                          if (input) input.click();
                        }}
                      >
                        <div className="task-tracker-dropzone__label">
                          Drag & drop files or click
                        </div>
                        <input
                          id="task-tracker-file-current"
                          type="file"
                          multiple
                          className="task-tracker-dropzone__input"
                          onChange={(event) => uploadTaskTrackerFiles(taskTrackerEditId, event.target.files)}
                        />
                      </div>
                      <div className="task-tracker-files">
                        {taskTrackerFilesError[taskTrackerEditId] && (
                          <div className="text-danger small">{taskTrackerFilesError[taskTrackerEditId]}</div>
                        )}
                        {taskTrackerFilesUploading[taskTrackerEditId] && (
                          <div className="text-muted small">Uploading…</div>
                        )}
                        {!taskTrackerFilesById[taskTrackerEditId] && !taskTrackerFilesLoading[taskTrackerEditId] && (
                          <button
                            type="button"
                            className="btn btn-link btn-sm p-0"
                            onClick={() => fetchTaskTrackerFiles(taskTrackerEditId)}
                          >
                            Load files
                          </button>
                        )}
                        {taskTrackerFilesLoading[taskTrackerEditId] && (
                          <div className="text-muted small">Loading…</div>
                        )}
                        {Array.isArray(taskTrackerFilesById[taskTrackerEditId])
                          && taskTrackerFilesById[taskTrackerEditId].length > 0 && (
                            <ul className="list-unstyled small mb-0">
                              {taskTrackerFilesById[taskTrackerEditId].map((file) => (
                                <li key={file.id}>
                                  <a href={file.downloadUrl} target="_blank" rel="noreferrer">
                                    {file.filename}
                                  </a>
                                </li>
                              ))}
                            </ul>
                          )}
                      </div>
                    </>
                  ) : (
                    <div className="text-muted small">Save the entry to upload files.</div>
                  )}
                </div>
                <div className="col-12 d-flex gap-2 justify-content-end">
                  {taskTrackerEditId && (
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-sm"
                      onClick={resetTaskTrackerDraft}
                      disabled={taskTrackerSaving}
                    >
                      Cancel
                    </button>
                  )}
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    onClick={submitTaskTracker}
                    disabled={taskTrackerSaving}
                  >
                    {taskTrackerSaving ? 'Saving…' : (taskTrackerEditId ? 'Save entry' : 'Add entry')}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div className="card shadow-sm">
            <div className="card-body">
              {taskTrackerLoading && <div className="text-muted">Loading task tracker…</div>}
              {!taskTrackerLoading && taskTrackerItems.length === 0 && (
                <div className="text-muted">No task tracker entries yet.</div>
              )}
              {!taskTrackerLoading && taskTrackerItems.length > 0 && (
                <div className="table-responsive">
                  <table className="table table-sm table-bordered table-striped align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Responsible</th>
                        <th>Tasks</th>
                        <th />
                      </tr>
                    </thead>
                    <tbody>
                      {taskTrackerItems.map((entry) => (
                        <tr key={entry.id}>
                          <td>{formatDateDisplay(entry.date)}</td>
                          <td>{formatDisplayValue(entry.category)}</td>
                          <td>
                            <div>{formatDisplayValue(entry.description)}</div>
                          </td>
                          <td>{formatDisplayValue(entry.responsible)}</td>
                          <td>
                            {Array.isArray(entry.tasks)
                              ? entry.tasks.map((task, index) => renderTaskTrackerTask(task, index))
                              : ''}
                          </td>
                          <td className="text-nowrap">
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-secondary"
                              onClick={() => startTaskTrackerEdit(entry)}
                            >
                              Edit
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {activeTab === 'reports' && (
        <div className="d-flex flex-column gap-3">
          <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
              <h2 className="h5 mb-0">Reports</h2>
              <div className="text-muted small">Tenant: IKEA</div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <select
                className="form-select form-select-sm"
                value={selectedReportId}
                onChange={(event) => setSelectedReportId(event.target.value)}
                disabled={reportLoading || reportList.length === 0}
                style={{ minWidth: 260 }}
              >
                <option value="">Select a report…</option>
                {reportList.map((report) => (
                  <option key={report.repid} value={report.repid}>{report.reptitle || report.repshort || `Report ${report.repid}`}</option>
                ))}
              </select>
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm"
                onClick={fetchReports}
                disabled={reportLoading}
              >
                Refresh
              </button>
              {selectedReportId && (
                <a className="btn btn-outline-primary btn-sm" href={`/report/${selectedReportId}`} target="_blank" rel="noreferrer">
                  Open
                </a>
              )}
            </div>
          </div>

          {reportError && (
            <div className="alert alert-danger" role="alert">
              {reportError}
            </div>
          )}

          {reportMetaError && (
            <div className="alert alert-danger" role="alert">
              {reportMetaError}
            </div>
          )}

          {selectedReportId ? (
            <div className="border rounded shadow-sm p-3">
              {reportMetaLoading && <div className="text-muted">Loading report…</div>}
              {!reportMetaLoading && reportMeta && (
                <>
                  <div
                    id="react-datatables-report"
                    data-auto-mount="false"
                    data-repid={selectedReportId}
                    data-reptitle={reportMeta.reptitle || ''}
                    data-repdesc={reportMeta.repdesc || ''}
                    data-repparam={
                      typeof reportMeta.repparam === 'string'
                        ? reportMeta.repparam
                        : JSON.stringify(reportMeta.repparam || {})
                    }
                  />
                  <DataTablesReport
                    key={selectedReportId}
                    repid={Number(selectedReportId)}
                    reptitle={reportMeta.reptitle || ''}
                    repdesc={reportMeta.repdesc || ''}
                    repparam={reportMeta.repparam || {}}
                  />
                </>
              )}
            </div>
          ) : (
            <div className="text-muted">Select a report to view the table.</div>
          )}
        </div>
      )}
    </div>
  );
};

export default SmartsheetPivotPage;
