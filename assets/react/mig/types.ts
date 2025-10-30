export type PlanningServer = {
  id: number;
  containerId: number;
  hostname: string;
  application: string | null;
  method: string;
  status?: string | null;
  calendarId: number | null;
  slotId: number | null;
  calendar?: { id: number; name: string | null; method: string | null } | null;
  slot?: { id: number; label: string | null; startsAt: string | null; endsAt: string | null } | null;
  scheduledStart: string | null;
  scheduledEnd: string | null;
};

export type PlanningContainer = {
  id: number;
  waveId: number;
  name: string;
  notes: string | null;
  applicationId: number | null;
  application: {
    id: number;
    name: string | null;
    ci: string | null;
    environment: string | null;
  } | null;
  servers: PlanningServer[];
};

export type PlanningWave = {
  id: number;
  projectId: number;
  name: string;
  status: string;
  startAt: string | null;
  endAt: string | null;
  containers: PlanningContainer[];
};

export type PlanningProject = {
  id: number;
  tenantId: number;
  name: string;
  status: string;
  createdAt: string | null;
  waves: PlanningWave[];
};

export type PlanningTreeResponse = {
  projects: PlanningProject[];
  methods: string[];
  summary: {
    totalServers: number;
    byMethod: Record<string, number>;
  };
};

export type ProgressSummary = {
  total: number;
  open: number;
  migrated: number;
  failed: number;
  completionPercent: number;
};

export type ManagementOverviewWave = {
  id: number;
  name: string;
  status: string;
  startAt: string | null;
  endAt: string | null;
  durationDays: number | null;
  progress: ProgressSummary;
  methodBreakdown: Record<string, number>;
};

export type ApplicationScore = {
  key: string;
  applicationId: number | null;
  name: string;
  ci: string | null;
  environment: string | null;
  containerCount: number;
  containers: Array<{ id: number; name: string }>;
  serverCount: number;
  open: number;
  migrated: number;
  failed: number;
  completionPercent: number;
};

export type ManagementOverview = {
  project: {
    id: number;
    name: string;
    status: string;
    createdAt: string | null;
  };
  timeline: {
    start: string | null;
    end: string | null;
    durationDays: number | null;
    waves: ManagementOverviewWave[];
  };
  summary: {
    totalWaves: number;
    totalContainers: number;
    totalServers: number;
    totalApplications: number;
    progress: ProgressSummary;
    methodBreakdown: Record<string, number>;
  };
  applications: ApplicationScore[];
};

export type CalendarSlot = {
  id: number;
  calendarId: number;
  label: string;
  startsAt: string | null;
  endsAt: string | null;
  capacity: number;
  notes: string | null;
};

export type CalendarInfo = {
  id: number;
  method: string;
  name: string;
  description: string | null;
  timezone: string;
  activeFrom: string | null;
  activeTo: string | null;
  createdAt: string | null;
  updatedAt: string | null;
  slots: CalendarSlot[];
};

export type CalendarListResponse = {
  methods: string[];
  items: CalendarInfo[];
};
