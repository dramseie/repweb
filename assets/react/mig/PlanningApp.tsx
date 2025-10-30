import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  fetchPlanningTree,
  fetchCalendars,
  fetchManagementOverview,
  patchServer,
  createCalendar,
  createSlot,
  updateSlot,
  deleteSlot,
  createProject,
  createWave,
  createContainer,
} from './api';
import type {
  CalendarInfo,
  CalendarSlot,
  ManagementOverview,
  ManagementOverviewWave,
  ApplicationScore,
  PlanningContainer,
  PlanningProject,
  PlanningServer,
  PlanningTreeResponse,
  PlanningWave,
} from './types';

type CalendarFormState = {
  name: string;
  method: string;
  timezone: string;
  description: string;
  activeFrom: string;
  activeTo: string;
};

type SlotDraftState = {
  id?: number;
  calendarId?: number;
  label: string;
  startsAt: string;
  endsAt: string;
  capacity: number;
  notes: string;
  isDeleted?: boolean;
};

type ProjectFormState = {
  name: string;
  tenantId: string;
  description: string;
};

type WaveFormState = {
  name: string;
  status: string;
  startAt: string;
  endAt: string;
};

type ContainerFormState = {
  name: string;
  notes: string;
};

type SelectionOverride = {
  projectId?: number | null;
  waveId?: number | null;
  containerId?: number | null;
};

type PlanningTab = 'overview' | 'projects' | 'waves' | 'containers' | 'calendars' | 'servers';

type ModalProps = {
  open: boolean;
  title: string;
  onClose: () => void;
  children: React.ReactNode;
  footer?: React.ReactNode;
  size?: 'sm' | 'lg' | 'xl';
};

const defaultProjectForm: ProjectFormState = {
  name: '',
  tenantId: '1',
  description: '',
};

const defaultWaveForm: WaveFormState = {
  name: '',
  status: 'Planned',
  startAt: '',
  endAt: '',
};

const defaultContainerForm: ContainerFormState = {
  name: '',
  notes: '',
};

const defaultCalendarForm: CalendarFormState = {
  name: '',
  method: '',
  timezone: 'UTC',
  description: '',
  activeFrom: '',
  activeTo: '',
};

const defaultSlotDraft: SlotDraftState = {
  label: '',
  startsAt: '',
  endsAt: '',
  capacity: 1,
  notes: '',
};

const waveStatusOptions = ['Planned', 'InProgress', 'Complete', 'OnHold'];

const sortCalendars = (items: CalendarInfo[]): CalendarInfo[] =>
  [...items].sort((a, b) => {
    if (a.method === b.method) {
      return a.name.localeCompare(b.name);
    }
    return a.method.localeCompare(b.method);
  });

const formatDisplayDate = (value: string | null): string => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleString();
  } catch (error) {
    return value;
  }
};

const toDateTimeLocal = (value: string | null): string => {
  if (!value) {
    return '';
  }
  const normalized = value.replace(' ', 'T');
  const match = normalized.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
  if (match) {
    return match[1];
  }
  return normalized.length >= 16 ? normalized.slice(0, 16) : normalized;
};

const slotToDraft = (slot: CalendarSlot): SlotDraftState => ({
  id: slot.id,
  calendarId: slot.calendarId,
  label: slot.label ?? '',
  startsAt: toDateTimeLocal(slot.startsAt),
  endsAt: toDateTimeLocal(slot.endsAt),
  capacity: Number.isFinite(slot.capacity) ? slot.capacity : 1,
  notes: slot.notes ?? '',
});

const Modal: React.FC<ModalProps> = ({ open, title, onClose, children, footer, size = 'lg' }) => {
  if (!open) return null;

  const sizeClass = size ? `modal-${size}` : '';

  const handleBackdropClick = (event: React.MouseEvent<HTMLDivElement>) => {
    if (event.target === event.currentTarget) {
      onClose();
    }
  };

  return (
    <div
      className="modal d-block"
      role="dialog"
      tabIndex={-1}
      aria-modal="true"
      style={{ backgroundColor: 'rgba(0, 0, 0, 0.4)' }}
      onMouseDown={handleBackdropClick}
    >
      <div className={`modal-dialog modal-dialog-centered ${sizeClass}`.trim()} onMouseDown={(event) => event.stopPropagation()}>
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{title}</h5>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Close" />
          </div>
          <div className="modal-body">{children}</div>
          {footer && <div className="modal-footer">{footer}</div>}
        </div>
      </div>
    </div>
  );
};

const PlanningApp: React.FC = () => {
  const [tree, setTree] = useState<PlanningTreeResponse | null>(null);
  const [calendars, setCalendars] = useState<CalendarInfo[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [calendarError, setCalendarError] = useState<string | null>(null);
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null);
  const [selectedWaveId, setSelectedWaveId] = useState<number | null>(null);
  const [selectedContainerId, setSelectedContainerId] = useState<number | null>(null);
  const [savingServerId, setSavingServerId] = useState<number | null>(null);
  const [calendarForm, setCalendarForm] = useState<CalendarFormState>({ ...defaultCalendarForm });
  const [projectForm, setProjectForm] = useState<ProjectFormState>({ ...defaultProjectForm });
  const [waveForm, setWaveForm] = useState<WaveFormState>({ ...defaultWaveForm });
  const [containerForm, setContainerForm] = useState<ContainerFormState>({ ...defaultContainerForm });
  const [overview, setOverview] = useState<ManagementOverview | null>(null);
  const [overviewLoading, setOverviewLoading] = useState<boolean>(false);
  const [overviewError, setOverviewError] = useState<string | null>(null);
  const [overviewStale, setOverviewStale] = useState<boolean>(true);
  const [savingProject, setSavingProject] = useState<boolean>(false);
  const [savingWave, setSavingWave] = useState<boolean>(false);
  const [savingContainer, setSavingContainer] = useState<boolean>(false);
  const [slotDraftList, setSlotDraftList] = useState<SlotDraftState[]>([]);
  const [slotDraftDialogOpen, setSlotDraftDialogOpen] = useState<boolean>(false);
  const [slotDraftDialogTitle, setSlotDraftDialogTitle] = useState<string>('');
  const [activeSlotCalendar, setActiveSlotCalendar] = useState<CalendarInfo | null>(null);
  const [slotDraftSnapshot, setSlotDraftSnapshot] = useState<CalendarSlot[]>([]);
  const [slotChangesSaving, setSlotChangesSaving] = useState<boolean>(false);
  const [activeTab, setActiveTab] = useState<PlanningTab>('overview');
  const [projectModalOpen, setProjectModalOpen] = useState<boolean>(false);
  const [waveModalOpen, setWaveModalOpen] = useState<boolean>(false);
  const [containerModalOpen, setContainerModalOpen] = useState<boolean>(false);

  const loadOverview = useCallback(async (projectId: number) => {
    setOverviewLoading(true);
    setOverviewError(null);
    try {
      const data = await fetchManagementOverview(projectId);
      setOverview(data);
    } catch (err) {
      setOverviewError(err instanceof Error ? err.message : 'Failed to load management overview');
    } finally {
      setOverviewLoading(false);
    }
  }, []);

  const applySelection = useCallback(
    (data: PlanningTreeResponse, preferred?: SelectionOverride) => {
      let projectId = selectedProjectId;
      if (preferred && Object.prototype.hasOwnProperty.call(preferred, 'projectId')) {
        projectId = preferred.projectId ?? null;
      }
      if (!projectId || !data.projects.some((project) => project.id === projectId)) {
        projectId = data.projects[0]?.id ?? null;
      }

      let waveId = selectedWaveId;
      if (preferred && Object.prototype.hasOwnProperty.call(preferred, 'waveId')) {
        waveId = preferred.waveId ?? null;
      }
      const project = projectId ? data.projects.find((item) => item.id === projectId) ?? null : null;
      if (!project || project.waves.length === 0) {
        waveId = null;
      } else if (!waveId || !project.waves.some((wave) => wave.id === waveId)) {
        waveId = project.waves[0]?.id ?? null;
      }

      let containerId = selectedContainerId;
      if (preferred && Object.prototype.hasOwnProperty.call(preferred, 'containerId')) {
        containerId = preferred.containerId ?? null;
      }
      const wave = waveId ? project?.waves.find((item) => item.id === waveId) ?? null : null;
      if (!wave || wave.containers.length === 0) {
        containerId = null;
      } else if (!containerId || !wave.containers.some((container) => container.id === containerId)) {
        containerId = wave.containers[0]?.id ?? null;
      }

      setSelectedProjectId(projectId);
      setSelectedWaveId(waveId);
      setSelectedContainerId(containerId);
    },
    [selectedProjectId, selectedWaveId, selectedContainerId],
  );

  const reloadCalendars = useCallback(async () => {
    try {
      const data = await fetchCalendars();
      setCalendars(sortCalendars(data.items));
      if (!data.methods.includes(calendarForm.method)) {
        setCalendarForm((prev) => ({ ...prev, method: data.methods[0] ?? prev.method }));
      }
    } catch (err) {
      setCalendarError(err instanceof Error ? err.message : 'Failed to load calendars');
    }
  }, [calendarForm.method]);

  const reloadTree = useCallback(
    async (preferred?: SelectionOverride) => {
      try {
        const data = await fetchPlanningTree();
        setTree(data);
        applySelection(data, preferred);
        setOverviewStale(true);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to refresh planning tree');
      }
    },
    [applySelection],
  );

  const reloadAll = useCallback(
    async (preferred?: SelectionOverride) => {
      setLoading(true);
      setError(null);
      try {
        const [treeData, calendarData] = await Promise.all([fetchPlanningTree(), fetchCalendars()]);
        setTree(treeData);
        applySelection(treeData, preferred);
        setOverviewStale(true);
        setCalendars(sortCalendars(calendarData.items));
        if (!calendarData.methods.includes(calendarForm.method)) {
          setCalendarForm((prev) => ({ ...prev, method: calendarData.methods[0] ?? prev.method }));
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load planning data');
      } finally {
        setLoading(false);
      }
    },
    [applySelection, calendarForm.method],
  );

  useEffect(() => {
    void reloadAll();
  }, [reloadAll]);

  useEffect(() => {
    if (tree && tree.methods.length > 0 && !calendarForm.method) {
      setCalendarForm((prev) => ({ ...prev, method: tree.methods[0] }));
    }
  }, [tree, calendarForm.method]);

  useEffect(() => {
    if (selectedProjectId === null) {
      setOverview(null);
      setOverviewStale(false);
      setOverviewError(null);
      setOverviewLoading(false);
      return;
    }

    const needsLoad = overview === null || overview.project.id !== selectedProjectId || overviewStale;
    if (!needsLoad) {
      return;
    }

    let cancelled = false;
    void (async () => {
      try {
        await loadOverview(selectedProjectId);
      } finally {
        if (!cancelled) {
          setOverviewStale(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [selectedProjectId, overview, overviewStale, loadOverview]);

  const selectedProject: PlanningProject | null = useMemo(() => {
    if (!tree || selectedProjectId === null) return null;
    return tree.projects.find((project) => project.id === selectedProjectId) ?? null;
  }, [tree, selectedProjectId]);

  const selectedWave: PlanningWave | null = useMemo(() => {
    if (!selectedProject || selectedWaveId === null) return null;
    return selectedProject.waves.find((wave) => wave.id === selectedWaveId) ?? null;
  }, [selectedProject, selectedWaveId]);

  const selectedContainer: PlanningContainer | null = useMemo(() => {
    if (!selectedWave || selectedContainerId === null) return null;
    return selectedWave.containers.find((container) => container.id === selectedContainerId) ?? null;
  }, [selectedWave, selectedContainerId]);

  const servers: PlanningServer[] = selectedContainer?.servers ?? [];

  const calendarsById = useMemo(() => {
    const map = new Map<number, CalendarInfo>();
    calendars.forEach((calendar) => map.set(calendar.id, calendar));
    return map;
  }, [calendars]);

  const calendarsByMethod = useMemo(() => {
    const map = new Map<string, CalendarInfo[]>();
    calendars.forEach((calendar) => {
      const list = map.get(calendar.method) ?? [];
      list.push(calendar);
      map.set(calendar.method, list);
    });
    map.forEach((items) => items.sort((a, b) => a.name.localeCompare(b.name)));
    return map;
  }, [calendars]);

  const upsertCalendar = useCallback((calendar: CalendarInfo) => {
    setCalendars((prev) => {
      const existing = prev.findIndex((item) => item.id === calendar.id);
      if (existing === -1) {
        return sortCalendars([...prev, calendar]);
      }
      const next = [...prev];
      next[existing] = calendar;
      return sortCalendars(next);
    });
  }, []);

  const handleServerUpdate = useCallback(
    async (serverId: number, body: Record<string, unknown>) => {
      setSavingServerId(serverId);
      setError(null);
      try {
        await patchServer(serverId, body);
        await reloadTree();
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to update server');
      } finally {
        setSavingServerId(null);
      }
    },
    [reloadTree],
  );

  const handleCreateCalendar = useCallback(async () => {
    if (!calendarForm.name.trim()) {
      setCalendarError('Calendar name is required');
      return;
    }
    if (!calendarForm.method) {
      setCalendarError('Please select a migration method');
      return;
    }
    setCalendarError(null);
    try {
      const calendar = await createCalendar({
        name: calendarForm.name.trim(),
        method: calendarForm.method,
        timezone: calendarForm.timezone || 'UTC',
        description: calendarForm.description || null,
        activeFrom: calendarForm.activeFrom || null,
        activeTo: calendarForm.activeTo || null,
      });
      setCalendars((prev) => sortCalendars([...prev, calendar]));
      setCalendarForm((prev) => ({ ...prev, name: '', description: '', activeFrom: '', activeTo: '' }));
    } catch (err) {
      setCalendarError(err instanceof Error ? err.message : 'Failed to create calendar');
    }
  }, [calendarForm]);

  const openSlotDialog = useCallback(
    (calendar: CalendarInfo) => {
      setActiveSlotCalendar(calendar);
      setSlotDraftDialogTitle(`Manage Slots • ${calendar.name}`);
      const snapshot = calendar.slots ?? [];
      setSlotDraftSnapshot(snapshot);
      if (snapshot.length === 0) {
        setSlotDraftList([{ ...defaultSlotDraft, calendarId: calendar.id }]);
      } else {
        setSlotDraftList(snapshot.map(slotToDraft));
      }
      setCalendarError(null);
      setSlotDraftDialogOpen(true);
    },
    [],
  );

  const closeSlotDialog = useCallback(() => {
    if (slotChangesSaving) {
      return;
    }
    setSlotDraftDialogOpen(false);
    setActiveSlotCalendar(null);
    setSlotDraftList([]);
    setSlotDraftSnapshot([]);
    setSlotDraftDialogTitle('');
    setCalendarError(null);
  }, [slotChangesSaving]);

  const handleSlotDraftFieldChange = useCallback((index: number, field: keyof SlotDraftState, value: string | number) => {
    setSlotDraftList((prev) =>
      prev.map((draft, idx) => {
        if (idx !== index || draft.isDeleted) {
          return draft;
        }
        if (field === 'capacity') {
          const numeric = typeof value === 'number' ? value : Number(value);
          const nextCapacity = Number.isFinite(numeric) && numeric > 0 ? Math.floor(numeric) : 1;
          return { ...draft, capacity: nextCapacity };
        }
        return { ...draft, [field]: typeof value === 'string' ? value : value.toString() };
      }),
    );
  }, []);

  const handleAddSlotDraftRow = useCallback(() => {
    setSlotDraftList((prev) => [...prev, { ...defaultSlotDraft, calendarId: activeSlotCalendar?.id }]);
  }, [activeSlotCalendar]);

  const handleToggleSlotDeletion = useCallback((index: number) => {
    setSlotDraftList((prev) => {
      const draft = prev[index];
      if (!draft) {
        return prev;
      }
      if (!draft.id) {
        return prev.filter((_, idx) => idx !== index);
      }
      const next = [...prev];
      next[index] = { ...draft, isDeleted: !draft.isDeleted };
      return next;
    });
  }, []);

  const handleSaveSlotDrafts = useCallback(async () => {
    if (!activeSlotCalendar) {
      return;
    }
    const calendarId = activeSlotCalendar.id;
    const sanitized = slotDraftList.map((draft) => ({
      ...draft,
      label: draft.label.trim(),
      notes: draft.notes.trim(),
    }));
    const invalid = sanitized.find((draft) => !draft.isDeleted && (!draft.label || !draft.startsAt || !draft.endsAt));
    if (invalid) {
      setCalendarError('Slot label and window are required');
      return;
    }
    setSlotChangesSaving(true);
    setCalendarError(null);
    try {
      const existingMap = new Map(slotDraftSnapshot.map((slot) => [slot.id, slot]));
      for (const draft of sanitized) {
        if (draft.isDeleted) {
          if (draft.id) {
            const calendar = await deleteSlot(draft.id);
            upsertCalendar(calendar);
          }
          continue;
        }

        const payload: Record<string, unknown> = {
          label: draft.label,
          startsAt: draft.startsAt,
          endsAt: draft.endsAt,
          capacity: Number.isFinite(draft.capacity) && draft.capacity > 0 ? draft.capacity : 1,
          notes: draft.notes ? draft.notes : null,
        };

        if (!draft.id) {
          const calendar = await createSlot(calendarId, payload);
          upsertCalendar(calendar);
          continue;
        }

        const original = existingMap.get(draft.id);
        const originalStart = toDateTimeLocal(original?.startsAt ?? null);
        const originalEnd = toDateTimeLocal(original?.endsAt ?? null);
        const originalNotes = original?.notes ?? '';
        const draftNotes = draft.notes ?? '';
        const hasChanges =
          !original ||
          original.label !== draft.label ||
          originalStart !== draft.startsAt ||
          originalEnd !== draft.endsAt ||
          original.capacity !== draft.capacity ||
          originalNotes !== draftNotes;
        if (!hasChanges) {
          continue;
        }

        const calendar = await updateSlot(draft.id, payload);
        upsertCalendar(calendar);
      }

      setSlotDraftDialogOpen(false);
      setActiveSlotCalendar(null);
      setSlotDraftList([]);
      setSlotDraftSnapshot([]);
      setSlotDraftDialogTitle('');
    } catch (err) {
      setCalendarError(err instanceof Error ? err.message : 'Failed to save slots');
    } finally {
      setSlotChangesSaving(false);
    }
  }, [activeSlotCalendar, slotDraftList, slotDraftSnapshot, upsertCalendar]);

  const handleOverviewRefresh = useCallback(() => {
    if (selectedProjectId !== null) {
      setOverviewStale(true);
    }
  }, [selectedProjectId]);

  const openProjectModal = useCallback(() => {
    setProjectForm({ ...defaultProjectForm });
    setProjectModalOpen(true);
  }, []);

  const closeProjectModal = useCallback(() => {
    if (savingProject) {
      return;
    }
    setProjectModalOpen(false);
    setProjectForm({ ...defaultProjectForm });
  }, [savingProject]);

  const openWaveModal = useCallback(() => {
    setWaveForm((prev) => ({ ...defaultWaveForm, status: prev.status || defaultWaveForm.status }));
    setWaveModalOpen(true);
  }, []);

  const closeWaveModal = useCallback(() => {
    if (savingWave) {
      return;
    }
    setWaveModalOpen(false);
    setWaveForm((prev) => ({ ...defaultWaveForm, status: prev.status || defaultWaveForm.status }));
  }, [savingWave]);

  const openContainerModal = useCallback(() => {
    setContainerForm({ ...defaultContainerForm });
    setContainerModalOpen(true);
  }, []);

  const closeContainerModal = useCallback(() => {
    if (savingContainer) {
      return;
    }
    setContainerModalOpen(false);
    setContainerForm({ ...defaultContainerForm });
  }, [savingContainer]);

  const handleCreateProject = useCallback(async () => {
    const name = projectForm.name.trim();
    if (!name) {
      setError('Project name is required');
      return;
    }
    setSavingProject(true);
    setError(null);
    try {
      const id = await createProject({
        tenant_id: projectForm.tenantId.trim() || '1',
        name,
        description: projectForm.description.trim() ? projectForm.description.trim() : null,
      });
      setProjectForm({ ...defaultProjectForm });
      await reloadTree({ projectId: id, waveId: null, containerId: null });
      setProjectModalOpen(false);
      setActiveTab('projects');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create project');
    } finally {
      setSavingProject(false);
    }
  }, [projectForm, reloadTree]);

  const handleCreateWave = useCallback(async () => {
    if (!selectedProjectId) {
      setError('Select a project before adding a wave');
      return;
    }
    const name = waveForm.name.trim();
    if (!name) {
      setError('Wave name is required');
      return;
    }
    setSavingWave(true);
    setError(null);
    try {
      const payload: Record<string, unknown> = {
        name,
        status: waveForm.status || 'Planned',
      };
      if (waveForm.startAt) payload.start_at = new Date(waveForm.startAt).toISOString();
      if (waveForm.endAt) payload.end_at = new Date(waveForm.endAt).toISOString();
      const id = await createWave(selectedProjectId, payload);
      setWaveForm((prev) => ({ ...defaultWaveForm, status: prev.status }));
      await reloadTree({ projectId: selectedProjectId, waveId: id, containerId: null });
      setWaveModalOpen(false);
      setActiveTab('waves');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create wave');
    } finally {
      setSavingWave(false);
    }
  }, [selectedProjectId, waveForm, reloadTree]);

  const handleCreateContainer = useCallback(async () => {
    if (!selectedProjectId || !selectedWaveId) {
      setError('Select a wave before adding a container');
      return;
    }
    const name = containerForm.name.trim();
    if (!name) {
      setError('Container name is required');
      return;
    }
    setSavingContainer(true);
    setError(null);
    try {
      const id = await createContainer(selectedWaveId, {
        name,
        notes: containerForm.notes.trim() ? containerForm.notes.trim() : null,
      });
      setContainerForm({ ...defaultContainerForm });
      await reloadTree({ projectId: selectedProjectId, waveId: selectedWaveId, containerId: id });
      setContainerModalOpen(false);
      setActiveTab('containers');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create container');
    } finally {
      setSavingContainer(false);
    }
  }, [selectedProjectId, selectedWaveId, containerForm, reloadTree]);

  const methods = tree?.methods ?? [];

  const tabs: Array<{ id: PlanningTab; label: string }> = [
    { id: 'overview', label: 'Overview' },
    { id: 'projects', label: 'Projects' },
    { id: 'waves', label: 'Waves' },
    { id: 'containers', label: 'Containers' },
    { id: 'calendars', label: 'Calendars' },
    { id: 'servers', label: 'Servers' },
  ];

  const totalProjects = tree?.projects.length ?? 0;
  const totalWaves = tree ? tree.projects.reduce((count, project) => count + project.waves.length, 0) : 0;
  const totalContainers = tree
    ? tree.projects.reduce(
        (count, project) => count + project.waves.reduce((waveCount, wave) => waveCount + wave.containers.length, 0),
        0,
      )
    : 0;

  const renderTabContent = () => {
    if (loading) {
      return (
        <div className="d-flex justify-content-center py-5">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Loading…</span>
          </div>
        </div>
      );
    }

    switch (activeTab) {
      case 'overview':
        return (
          <div className="d-flex flex-column gap-4">
            <ManagementOverviewSection
              overview={overview}
              loading={overviewLoading}
              error={overviewError}
              onRefresh={handleOverviewRefresh}
            />
            <div className="row g-3">
              <div className="col-lg-4">
                <div className="card border-0 shadow-sm h-100">
                  <div className="card-body">
                    <h6 className="text-uppercase text-muted small mb-3">Portfolio Summary</h6>
                    <div className="d-flex justify-content-between mb-2">
                      <span>Total projects</span>
                      <span className="fw-semibold">{totalProjects}</span>
                    </div>
                    <div className="d-flex justify-content-between mb-2">
                      <span>Total waves</span>
                      <span className="fw-semibold">{totalWaves}</span>
                    </div>
                    <div className="d-flex justify-content-between mb-2">
                      <span>Total containers</span>
                      <span className="fw-semibold">{totalContainers}</span>
                    </div>
                    <div className="d-flex justify-content-between mb-2">
                      <span>Total servers</span>
                      <span className="fw-semibold">{tree?.summary.totalServers ?? 0}</span>
                    </div>
                    <hr />
                    <div className="small text-uppercase text-muted mb-1">By method</div>
                    {methods.length === 0 && <div className="small text-muted">No migration methods configured.</div>}
                    {methods.map((method) => (
                      <div className="d-flex justify-content-between small" key={method}>
                        <span>{method}</span>
                        <span>{tree?.summary.byMethod[method] ?? 0}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
              <div className="col-lg-8">
                <div className="card border-0 shadow-sm h-100">
                  <div className="card-body">
                    <h6 className="text-uppercase text-muted small mb-3">Current Selection</h6>
                    <div className="mb-3">
                      <div className="small text-uppercase text-muted mb-1">Project</div>
                      <div className="fw-semibold">{selectedProject ? selectedProject.name : 'None selected'}</div>
                      {selectedProject && (
                        <div className="small text-muted">
                          Status {selectedProject.status}
                          {selectedProject.createdAt ? ` · Created ${formatDisplayDate(selectedProject.createdAt)}` : ''}
                        </div>
                      )}
                    </div>
                    <div className="mb-3">
                      <div className="small text-uppercase text-muted mb-1">Wave</div>
                      {selectedWave ? (
                        <>
                          <div className="fw-semibold">{selectedWave.name}</div>
                          <div className="small text-muted">
                            {formatDisplayDate(selectedWave.startAt)} → {formatDisplayDate(selectedWave.endAt)} · {selectedWave.status}
                          </div>
                        </>
                      ) : (
                        <div className="text-muted">Select a wave to see details.</div>
                      )}
                    </div>
                    <div className="mb-3">
                      <div className="small text-uppercase text-muted mb-1">Container</div>
                      {selectedContainer ? (
                        <>
                          <div className="fw-semibold">{selectedContainer.name}</div>
                          <div className="small text-muted">Servers {selectedContainer.servers.length}</div>
                          {selectedContainer.application && (
                            <div className="small text-muted">
                              {selectedContainer.application.name ?? selectedContainer.application.ci}
                              {selectedContainer.application.environment ? ` · ${selectedContainer.application.environment}` : ''}
                            </div>
                          )}
                        </>
                      ) : (
                        <div className="text-muted">Select a container to view assigned servers.</div>
                      )}
                    </div>
                    <div>
                      <div className="small text-uppercase text-muted mb-1">Calendars</div>
                      <div className="fw-semibold mb-1">{calendars.length} configured</div>
                      <div className="small text-muted">Use the Calendars tab to manage migration slots.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        );
      case 'projects':
        return (
          <div className="d-flex flex-column gap-3">
            <div className="d-flex flex-wrap justify-content-between aligners-center gap-2">
              <div>
                <h2 className="h5 mb-0">Projects</h2>
                <p className="text-muted small mb-0">Select a project to manage its waves and containers.</p>
              </div>
              <button className="btn btn-primary btn-sm" type="button" onClick={openProjectModal}>
                New project
              </button>
            </div>
            <div className="list-group">
              {tree && tree.projects.length > 0 ? (
                tree.projects.map((project) => (
                  <button
                    key={project.id}
                    type="button"
                    className={`list-group-item list-group-item-action ${selectedProjectId === project.id ? 'active' : ''}`}
                    onClick={() => {
                      setSelectedProjectId(project.id);
                      setSelectedWaveId(project.waves[0]?.id ?? null);
                      setSelectedContainerId(project.waves[0]?.containers[0]?.id ?? null);
                    }}
                  >
                    <div className="d-flex justify-content-between align-items-center">
                      <span>{project.name}</span>
                      <span className="badge text-bg-light text-uppercase">{project.status}</span>
                    </div>
                    <div className="small text-muted">
                      {project.waves.length} wave(s) · {project.waves.reduce((total, wave) => total + wave.containers.length, 0)} container(s)
                    </div>
                  </button>
                ))
              ) : (
                <div className="list-group-item text-muted small">No migration projects defined yet.</div>
              )}
            </div>
          </div>
        );
      case 'waves':
        if (!selectedProject) {
          return (
            <div className="alert alert-info mb-0" role="alert">
              Select a project on the Projects tab to manage waves.
            </div>
          );
        }
        return (
          <div className="d-flex flex-column gap-3">
            <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <h2 className="h5 mb-0">Waves for {selectedProject.name}</h2>
                <p className="text-muted small mb-0">{selectedProject.waves.length} wave(s) defined.</p>
              </div>
              <button className="btn btn-primary btn-sm" type="button" onClick={openWaveModal}>
                New wave
              </button>
            </div>
            <div className="list-group">
              {selectedProject.waves.length > 0 ? (
                selectedProject.waves.map((wave) => (
                  <button
                    key={wave.id}
                    type="button"
                    className={`list-group-item list-group-item-action ${selectedWaveId === wave.id ? 'active' : ''}`}
                    onClick={() => {
                      setSelectedWaveId(wave.id);
                      setSelectedContainerId(wave.containers[0]?.id ?? null);
                    }}
                  >
                    <div className="d-flex justify-content-between align-items-center">
                      <span>{wave.name}</span>
                      <span className="badge text-bg-light text-uppercase">{wave.status}</span>
                    </div>
                    <div className="small text-muted">
                      {formatDisplayDate(wave.startAt)} → {formatDisplayDate(wave.endAt)} · {wave.containers.length} container(s)
                    </div>
                  </button>
                ))
              ) : (
                <div className="list-group-item text-muted small">No waves defined for this project.</div>
              )}
            </div>
          </div>
        );
      case 'containers':
        if (!selectedProject) {
          return (
            <div className="alert alert-info mb-0" role="alert">
              Select a project first to view containers.
            </div>
          );
        }
        if (!selectedWave) {
          return (
            <div className="alert alert-info mb-0" role="alert">
              Choose a wave on the Waves tab to manage containers.
            </div>
          );
        }
        return (
          <div className="d-flex flex-column gap-3">
            <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <h2 className="h5 mb-0">Containers in {selectedWave.name}</h2>
                <p className="text-muted small mb-0">{selectedWave.containers.length} container(s) defined.</p>
              </div>
              <button className="btn btn-primary btn-sm" type="button" onClick={openContainerModal}>
                New container
              </button>
            </div>
            <div className="list-group">
              {selectedWave.containers.length > 0 ? (
                selectedWave.containers.map((container) => (
                  <button
                    key={container.id}
                    type="button"
                    className={`list-group-item list-group-item-action ${selectedContainerId === container.id ? 'active' : ''}`}
                    onClick={() => setSelectedContainerId(container.id)}
                  >
                    <div className="fw-semibold">{container.name}</div>
                    {container.application && (
                      <div className="small text-muted">
                        {container.application.name ?? container.application.ci}
                        {container.application.environment ? ` · ${container.application.environment}` : ''}
                      </div>
                    )}
                    <div className="small text-muted">{container.servers.length} server(s)</div>
                  </button>
                ))
              ) : (
                <div className="list-group-item text-muted small">No containers defined for this wave.</div>
              )}
            </div>
          </div>
        );
      case 'calendars':
        return (
          <div className="d-flex flex-column gap-4">
            <div className="card border-0 shadow-sm">
              <div className="card-body">
                <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div>
                    <h2 className="h5 mb-0">Calendars</h2>
                    <p className="text-muted small mb-0">Create calendars and manage their slots.</p>
                  </div>
                  <button className="btn btn-outline-secondary btn-sm" type="button" onClick={() => void reloadCalendars()}>
                    Reload
                  </button>
                </div>
                {calendarError && (
                  <div className="alert alert-warning py-2" role="alert">
                    {calendarError}
                  </div>
                )}
                <div className="row g-3">
                  <div className="col-lg-6 col-xl-4">
                    <div className="border rounded p-3 h-100 bg-light-subtle">
                      <h6 className="text-uppercase text-muted small mb-3">New calendar</h6>
                      <input
                        className="form-control form-control-sm mb-2"
                        placeholder="Calendar name"
                        value={calendarForm.name}
                        onChange={(event) => setCalendarForm((prev) => ({ ...prev, name: event.target.value }))}
                      />
                      <div className="row g-2 mb-2">
                        <div className="col">
                          <select
                            className="form-select form-select-sm"
                            value={calendarForm.method}
                            onChange={(event) => setCalendarForm((prev) => ({ ...prev, method: event.target.value }))}
                          >
                            <option value="">Method…</option>
                            {methods.map((method) => (
                              <option key={method} value={method}>
                                {method}
                              </option>
                            ))}
                          </select>
                        </div>
                        <div className="col">
                          <input
                            className="form-control form-control-sm"
                            placeholder="Timezone"
                            value={calendarForm.timezone}
                            onChange={(event) => setCalendarForm((prev) => ({ ...prev, timezone: event.target.value }))}
                          />
                        </div>
                      </div>
                      <textarea
                        className="form-control form-control-sm mb-2"
                        placeholder="Description"
                        value={calendarForm.description}
                        onChange={(event) => setCalendarForm((prev) => ({ ...prev, description: event.target.value }))}
                        rows={2}
                      />
                      <div className="row g-2 mb-2">
                        <div className="col">
                          <input
                            className="form-control form-control-sm"
                            type="datetime-local"
                            value={calendarForm.activeFrom}
                            onChange={(event) => setCalendarForm((prev) => ({ ...prev, activeFrom: event.target.value }))}
                          />
                          <small className="text-muted">Active from</small>
                        </div>
                        <div className="col">
                          <input
                            className="form-control form-control-sm"
                            type="datetime-local"
                            value={calendarForm.activeTo}
                            onChange={(event) => setCalendarForm((prev) => ({ ...prev, activeTo: event.target.value }))}
                          />
                          <small className="text-muted">Active to</small>
                        </div>
                      </div>
                      <button className="btn btn-primary btn-sm w-100" type="button" onClick={() => void handleCreateCalendar()}>
                        Create calendar
                      </button>
                    </div>
                  </div>
                  <div className="col-lg-6 col-xl-8">
                    <div className="row g-3">
                      {calendars.length === 0 && (
                        <div className="col-12">
                          <div className="alert alert-secondary mb-0" role="alert">
                            No calendars defined yet.
                          </div>
                        </div>
                      )}
                      {calendars.map((calendar) => (
                        <div className="col-md-6" key={calendar.id}>
                          <div className="card border-0 shadow-sm h-100">
                            <div className="card-body d-flex flex-column">
                              <div className="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                  <div className="fw-semibold">{calendar.name}</div>
                                  <div className="small text-muted">
                                    {calendar.method} · {calendar.timezone}
                                  </div>
                                </div>
                                <span className="badge text-bg-light">{calendar.slots.length} slot(s)</span>
                              </div>
                              {calendar.description && <p className="small text-muted mb-2">{calendar.description}</p>}
                              {(calendar.activeFrom || calendar.activeTo) && (
                                <div className="small text-muted mb-2">
                                  Active {formatDisplayDate(calendar.activeFrom)} → {formatDisplayDate(calendar.activeTo)}
                                </div>
                              )}
                              {calendar.slots.slice(0, 3).map((slot) => (
                                <div className="small text-muted" key={slot.id}>
                                  • {slot.label} ({formatDisplayDate(slot.startsAt)} → {formatDisplayDate(slot.endsAt)})
                                </div>
                              ))}
                              {calendar.slots.length > 3 && (
                                <div className="small text-muted">+{calendar.slots.length - 3} more slot(s)</div>
                              )}
                              <div className="mt-auto pt-3">
                                <button
                                  className="btn btn-outline-primary btn-sm w-100"
                                  type="button"
                                  onClick={() => openSlotDialog(calendar)}
                                >
                                  Manage slots
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        );
      case 'servers':
        if (!selectedContainer) {
          return (
            <div className="alert alert-info mb-0" role="alert">
              Select a container on the Containers tab to assign calendars and slots.
            </div>
          );
        }
        return (
          <div className="d-flex flex-column gap-3">
            <div>
              <h2 className="h5 mb-1">Servers in {selectedContainer.name}</h2>
              <p className="text-muted small mb-0">Update migration method, calendar, and slot assignments for each server.</p>
            </div>
            {servers.length === 0 ? (
              <div className="alert alert-secondary mb-0" role="alert">
                No servers assigned to this container.
              </div>
            ) : (
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>Hostname</th>
                      <th>Application</th>
                      <th style={{ width: '16%' }}>Method</th>
                      <th style={{ width: '20%' }}>Calendar</th>
                      <th style={{ width: '20%' }}>Slot</th>
                      <th>Scheduled</th>
                    </tr>
                  </thead>
                  <tbody>
                    {servers.map((server) => {
                      const methodCalendars = calendarsByMethod.get(server.method) ?? [];
                      const selectedCalendar = server.calendarId ? calendarsById.get(server.calendarId) ?? null : null;
                      const slotOptions = selectedCalendar?.slots ?? [];
                      const isSaving = savingServerId === server.id;
                      return (
                        <tr key={server.id} className={isSaving ? 'table-warning' : ''}>
                          <td className="fw-semibold">{server.hostname}</td>
                          <td className="text-muted small">{server.application ?? '—'}</td>
                          <td>
                            <select
                              className="form-select form-select-sm"
                              value={server.method}
                              onChange={(event) => void handleServerUpdate(server.id, { method: event.target.value })}
                              disabled={isSaving}
                            >
                              {methods.map((method) => (
                                <option key={method} value={method}>
                                  {method}
                                </option>
                              ))}
                            </select>
                          </td>
                          <td>
                            <select
                              className="form-select form-select-sm"
                              value={server.calendarId ?? ''}
                              onChange={(event) => {
                                const value = event.target.value ? Number(event.target.value) : null;
                                void handleServerUpdate(server.id, { calendar_id: value, slot_id: null });
                              }}
                              disabled={isSaving}
                            >
                              <option value="">Unassigned</option>
                              {methodCalendars.map((calendar) => (
                                <option key={calendar.id} value={calendar.id}>
                                  {calendar.name}
                                </option>
                              ))}
                            </select>
                          </td>
                          <td>
                            <select
                              className="form-select form-select-sm"
                              value={server.slotId ?? ''}
                              onChange={(event) => {
                                const value = event.target.value ? Number(event.target.value) : null;
                                void handleServerUpdate(server.id, { slot_id: value });
                              }}
                              disabled={isSaving || !selectedCalendar}
                            >
                              <option value="">Unassigned</option>
                              {slotOptions.map((slot) => (
                                <option key={slot.id} value={slot.id}>
                                  {slot.label}
                                </option>
                              ))}
                            </select>
                          </td>
                          <td className="small text-muted">
                            {server.scheduledStart
                              ? `${formatDisplayDate(server.scheduledStart)} → ${formatDisplayDate(server.scheduledEnd)}`
                              : 'Not scheduled'}
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
      default:
        return null;
    }
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h1 className="h3 mb-1">Migration Planning</h1>
          <p className="text-muted mb-0">Coordinate application server moves using calendars and slots.</p>
        </div>
        <div className="d-flex gap-2">
          <button className="btn btn-outline-secondary btn-sm" onClick={() => void reloadAll()} disabled={loading}>
            Refresh data
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      <div className="card shadow-sm">
        <div className="card-header border-bottom-0 pb-0">
          <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <ul className="nav nav-tabs card-header-tabs">
              {tabs.map((tab) => (
                <li className="nav-item" key={tab.id}>
                  <button
                    type="button"
                    className={`nav-link ${activeTab === tab.id ? 'active' : ''}`}
                    onClick={() => setActiveTab(tab.id)}
                  >
                    {tab.label}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </div>
        <div className="card-body">{renderTabContent()}</div>
      </div>

      <Modal
        open={projectModalOpen}
        title="Create Project"
        onClose={closeProjectModal}
        footer={
          <>
            <button type="button" className="btn btn-outline-secondary" onClick={closeProjectModal} disabled={savingProject}>
              Cancel
            </button>
            <button
              type="button"
              className="btn btn-primary"
              onClick={() => void handleCreateProject()}
              disabled={savingProject}
            >
              {savingProject ? 'Creating…' : 'Create project'}
            </button>
          </>
        }
        size="lg"
      >
        <div className="vstack gap-3">
          <div>
            <label className="form-label small fw-semibold">Project name</label>
            <input
              className="form-control"
              placeholder="Enter project name"
              value={projectForm.name}
              onChange={(event) => setProjectForm((prev) => ({ ...prev, name: event.target.value }))}
              disabled={savingProject}
            />
          </div>
          <div>
            <label className="form-label small fw-semibold">Tenant ID</label>
            <input
              className="form-control"
              placeholder="Tenant identifier"
              value={projectForm.tenantId}
              onChange={(event) => setProjectForm((prev) => ({ ...prev, tenantId: event.target.value }))}
              disabled={savingProject}
            />
          </div>
          <div>
            <label className="form-label small fw-semibold">Description</label>
            <textarea
              className="form-control"
              rows={3}
              placeholder="Optional project notes"
              value={projectForm.description}
              onChange={(event) => setProjectForm((prev) => ({ ...prev, description: event.target.value }))}
              disabled={savingProject}
            />
          </div>
        </div>
      </Modal>

      <Modal
        open={waveModalOpen}
        title="Create Wave"
        onClose={closeWaveModal}
        footer={
          <>
            <button type="button" className="btn btn-outline-secondary" onClick={closeWaveModal} disabled={savingWave}>
              Cancel
            </button>
            <button type="button" className="btn btn-primary" onClick={() => void handleCreateWave()} disabled={savingWave}>
              {savingWave ? 'Creating…' : 'Create wave'}
            </button>
          </>
        }
        size="lg"
      >
        <div className="vstack gap-3">
          <div className="small text-muted">
            {selectedProject ? `Project ${selectedProject.name}` : 'Select a project before creating waves.'}
          </div>
          <div>
            <label className="form-label small fw-semibold">Wave name</label>
            <input
              className="form-control"
              placeholder="Wave identifier"
              value={waveForm.name}
              onChange={(event) => setWaveForm((prev) => ({ ...prev, name: event.target.value }))}
              disabled={savingWave}
            />
          </div>
          <div className="row g-3">
            <div className="col-md-4">
              <label className="form-label small fw-semibold">Status</label>
              <select
                className="form-select"
                value={waveForm.status}
                onChange={(event) => setWaveForm((prev) => ({ ...prev, status: event.target.value }))}
                disabled={savingWave}
              >
                {waveStatusOptions.map((status) => (
                  <option key={status} value={status}>
                    {status}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">Start (optional)</label>
              <input
                className="form-control"
                type="datetime-local"
                value={waveForm.startAt}
                onChange={(event) => setWaveForm((prev) => ({ ...prev, startAt: event.target.value }))}
                disabled={savingWave}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">End (optional)</label>
              <input
                className="form-control"
                type="datetime-local"
                value={waveForm.endAt}
                onChange={(event) => setWaveForm((prev) => ({ ...prev, endAt: event.target.value }))}
                disabled={savingWave}
              />
            </div>
          </div>
        </div>
      </Modal>

      <Modal
        open={containerModalOpen}
        title="Create Container"
        onClose={closeContainerModal}
        footer={
          <>
            <button type="button" className="btn btn-outline-secondary" onClick={closeContainerModal} disabled={savingContainer}>
              Cancel
            </button>
            <button type="button" className="btn btn-primary" onClick={() => void handleCreateContainer()} disabled={savingContainer}>
              {savingContainer ? 'Creating…' : 'Create container'}
            </button>
          </>
        }
        size="lg"
      >
        <div className="vstack gap-3">
          <div className="small text-muted">
            {selectedWave
              ? `Wave ${selectedWave.name}`
              : 'Select a wave on the Waves tab before creating containers.'}
          </div>
          <div>
            <label className="form-label small fw-semibold">Container name</label>
            <input
              className="form-control"
              placeholder="Container identifier"
              value={containerForm.name}
              onChange={(event) => setContainerForm((prev) => ({ ...prev, name: event.target.value }))}
              disabled={savingContainer}
            />
          </div>
          <div>
            <label className="form-label small fw-semibold">Notes</label>
            <textarea
              className="form-control"
              rows={3}
              placeholder="Optional container notes"
              value={containerForm.notes}
              onChange={(event) => setContainerForm((prev) => ({ ...prev, notes: event.target.value }))}
              disabled={savingContainer}
            />
          </div>
        </div>
      </Modal>

      <Modal
        open={slotDraftDialogOpen}
        title={slotDraftDialogTitle || 'Manage Slots'}
        onClose={closeSlotDialog}
        footer={
          <>
            <button type="button" className="btn btn-outline-secondary" onClick={closeSlotDialog} disabled={slotChangesSaving}>
              Cancel
            </button>
            <button type="button" className="btn btn-primary" onClick={() => void handleSaveSlotDrafts()} disabled={slotChangesSaving}>
              {slotChangesSaving ? 'Saving…' : 'Save changes'}
            </button>
          </>
        }
        size="xl"
      >
        <div className="vstack gap-3">
          {calendarError && (
            <div className="alert alert-warning py-2 mb-0" role="alert">
              {calendarError}
            </div>
          )}
          <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <div className="fw-semibold">{activeSlotCalendar?.name ?? 'Calendar slots'}</div>
              {activeSlotCalendar && (
                <div className="small text-muted">
                  {activeSlotCalendar.method} · {activeSlotCalendar.timezone}
                </div>
              )}
            </div>
            <button
              type="button"
              className="btn btn-outline-secondary btn-sm"
              onClick={handleAddSlotDraftRow}
              disabled={slotChangesSaving || !activeSlotCalendar}
            >
              Add slot
            </button>
          </div>
          <div className="vstack gap-3">
            {slotDraftList.length === 0 && (
              <div className="alert alert-secondary mb-0" role="alert">
                No slots defined yet.
              </div>
            )}
            {slotDraftList.map((draft, index) => {
              const key = draft.id ? `slot-${draft.id}` : `draft-${index}`;
              const isDeleted = Boolean(draft.isDeleted);
              return (
                <div key={key} className={`border rounded p-3 ${isDeleted ? 'bg-light text-muted' : ''}`}>
                  <div className="row g-2 align-items-end">
                    <div className="col-md-3">
                      <label className="form-label small fw-semibold">Label</label>
                      <input
                        className="form-control form-control-sm"
                        value={draft.label}
                        onChange={(event) => handleSlotDraftFieldChange(index, 'label', event.target.value)}
                        disabled={slotChangesSaving || isDeleted}
                      />
                    </div>
                    <div className="col-md-3">
                      <label className="form-label small fw-semibold">Window start</label>
                      <input
                        className="form-control form-control-sm"
                        type="datetime-local"
                        value={draft.startsAt}
                        onChange={(event) => handleSlotDraftFieldChange(index, 'startsAt', event.target.value)}
                        disabled={slotChangesSaving || isDeleted}
                      />
                    </div>
                    <div className="col-md-3">
                      <label className="form-label small fw-semibold">Window end</label>
                      <input
                        className="form-control form-control-sm"
                        type="datetime-local"
                        value={draft.endsAt}
                        onChange={(event) => handleSlotDraftFieldChange(index, 'endsAt', event.target.value)}
                        disabled={slotChangesSaving || isDeleted}
                      />
                    </div>
                    <div className="col-md-2">
                      <label className="form-label small fw-semibold">Capacity</label>
                      <input
                        className="form-control form-control-sm"
                        type="number"
                        min={1}
                        value={draft.capacity}
                        onChange={(event) => handleSlotDraftFieldChange(index, 'capacity', Number(event.target.value))}
                        disabled={slotChangesSaving || isDeleted}
                      />
                    </div>
                    <div className="col-md-1 d-flex justify-content-end">
                      <button
                        type="button"
                        className={`btn btn-sm ${isDeleted ? 'btn-outline-secondary' : 'btn-outline-danger'}`}
                        onClick={() => handleToggleSlotDeletion(index)}
                        disabled={slotChangesSaving}
                      >
                        {isDeleted ? 'Restore' : 'Remove'}
                      </button>
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">Notes</label>
                      <textarea
                        className="form-control form-control-sm"
                        rows={2}
                        value={draft.notes}
                        onChange={(event) => handleSlotDraftFieldChange(index, 'notes', event.target.value)}
                        disabled={slotChangesSaving || isDeleted}
                      />
                    </div>
                  </div>
                  {isDeleted && <div className="mt-2 small">Slot will be deleted when you save changes.</div>}
                </div>
              );
            })}
          </div>
        </div>
      </Modal>
    </div>
  );
};

type ManagementOverviewSectionProps = {
  overview: ManagementOverview | null;
  loading: boolean;
  error: string | null;
  onRefresh: () => void;
};

function ManagementOverviewSection({ overview, loading, error, onRefresh }: ManagementOverviewSectionProps) {
  if (loading) {
    return (
      <div className="card border-0 shadow-sm">
        <div className="card-body text-center py-5">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Loading…</span>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="alert alert-warning d-flex justify-content-between align-items-center mb-0" role="alert">
            <span>{error}</span>
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={onRefresh}>
              Retry
            </button>
          </div>
        </div>
      </div>
    );
  }

  if (!overview) {
    return (
      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="text-muted">Select a project to load management insight.</div>
        </div>
      </div>
    );
  }

  const {
    project,
    summary,
    timeline,
    applications,
  } = overview;
  const progress = summary.progress;
  const progressPercent = Math.max(0, Math.min(100, Math.round(progress.completionPercent ?? 0)));
  const methodEntries = Object.entries(summary.methodBreakdown ?? {});
  const topApplications = applications.slice(0, 5);

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-body">
        <div className="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h2 className="h5 mb-1">{project.name}</h2>
            <div className="small text-muted">
              Status {project.status}
              {project.createdAt ? ` · Created ${formatDisplayDate(project.createdAt)}` : ''}
            </div>
          </div>
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={onRefresh}>
            Refresh overview
          </button>
        </div>

        <div className="row g-3">
          <div className="col-lg-4">
            <div className="border rounded p-3 h-100">
              <h6 className="text-uppercase text-muted small mb-3">Progress</h6>
              <div className="mb-3">
                <div className="fw-semibold mb-1">{formatPercent(progress.completionPercent)}</div>
                <div className="progress" role="progressbar" aria-valuenow={progressPercent} aria-valuemin={0} aria-valuemax={100}>
                  <div className="progress-bar" style={{ width: `${progressPercent}%` }} />
                </div>
                <div className="small text-muted mt-2">
                  {progress.migrated} migrated · {progress.open} open · {progress.failed} failed
                </div>
              </div>
              <div className="small text-uppercase text-muted mb-2">Totals</div>
              <div className="d-flex justify-content-between small mb-1">
                <span>Waves</span>
                <span>{summary.totalWaves}</span>
              </div>
              <div className="d-flex justify-content-between small mb-1">
                <span>Containers</span>
                <span>{summary.totalContainers}</span>
              </div>
              <div className="d-flex justify-content-between small mb-1">
                <span>Servers</span>
                <span>{summary.totalServers}</span>
              </div>
              <div className="d-flex justify-content-between small mb-3">
                <span>Applications</span>
                <span>{summary.totalApplications}</span>
              </div>
              <div className="small text-uppercase text-muted mb-2">By method</div>
              {methodEntries.length === 0 && <div className="small text-muted">No breakdown available.</div>}
              {methodEntries.map(([method, count]) => (
                <div className="d-flex justify-content-between small" key={method}>
                  <span>{method}</span>
                  <span>{count}</span>
                </div>
              ))}
            </div>
          </div>

          <div className="col-lg-4">
            <div className="border rounded p-3 h-100">
              <h6 className="text-uppercase text-muted small mb-3">Timeline</h6>
              <div className="small text-muted mb-2">
                {formatDisplayDate(timeline.start)} → {formatDisplayDate(timeline.end)}
              </div>
              <div className="small text-muted mb-3">Duration {formatDuration(timeline.durationDays)}</div>
              <div className="vstack gap-2">
                {timeline.waves.length === 0 && <div className="text-muted small">No wave schedule available.</div>}
                {timeline.waves.map((wave) => (
                  <div key={wave.id} className="border rounded px-3 py-2">
                    <div className="d-flex justify-content-between align-items-center mb-1">
                      <span className="fw-semibold">{wave.name}</span>
                      <span className="badge text-bg-light text-uppercase">{wave.status}</span>
                    </div>
                    <div className="small text-muted">
                      {formatDisplayDate(wave.startAt)} → {formatDisplayDate(wave.endAt)}
                    </div>
                    <div className="small text-muted">Duration {formatDuration(wave.durationDays)}</div>
                    <div className="small text-muted">
                      Migrated {wave.progress.migrated} · Open {wave.progress.open} · Failed {wave.progress.failed}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="col-lg-4">
            <div className="border rounded p-3 h-100">
              <h6 className="text-uppercase text-muted small mb-3">Top Applications</h6>
              {topApplications.length === 0 && <div className="text-muted small">No application metrics yet.</div>}
              {topApplications.length > 0 && (
                <div className="vstack gap-2">
                  {topApplications.map((app) => (
                    <div key={app.key} className="border rounded px-3 py-2">
                      <div className="d-flex justify-content-between align-items-center mb-1">
                        <span className="fw-semibold">{app.name}</span>
                        <span className="badge text-bg-light">{formatPercent(app.completionPercent)}</span>
                      </div>
                      <div className="small text-muted">
                        {app.serverCount} server(s) · {app.containerCount} container(s)
                      </div>
                      <div className="small text-muted">
                        Migrated {app.migrated} · Open {app.open} · Failed {app.failed}
                      </div>
                      {app.environment && <div className="small text-muted">Environment {app.environment}</div>}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return '—';
  }
  return `${Math.round(value)}%`;
}

function formatDuration(days: number | null | undefined): string {
  if (days === null || days === undefined || Number.isNaN(days)) {
    return '—';
  }
  const rounded = Math.round(days);
  if (rounded === 0) {
    return 'Same day';
  }
  if (rounded === 1) {
    return '1 day';
  }
  return `${rounded} days`;
}

export default PlanningApp;
