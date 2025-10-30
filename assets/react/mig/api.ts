import type {
  CalendarInfo,
  CalendarListResponse,
  ManagementOverview,
  PlanningTreeResponse,
  PlanningServer,
} from './types';

async function requestJson<T>(url: string, options: RequestInit = {}): Promise<T> {
  const headers = options.headers instanceof Headers
    ? options.headers
    : new Headers(options.headers);
  if (!headers.has('Accept')) headers.set('Accept', 'application/json');
  if (options.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');

  const response = await fetch(url, { ...options, headers });
  const text = await response.text();
  let payload: any = null;
  if (text) {
    try {
      payload = JSON.parse(text);
    } catch (error) {
      if (!response.ok) {
        throw new Error(text || response.statusText || 'Request failed');
      }
      throw error;
    }
  }

  if (!response.ok || (payload && payload.ok === false)) {
    const message = payload?.error || payload?.message || response.statusText || 'Request failed';
    throw new Error(message);
  }

  if (payload && typeof payload === 'object' && 'data' in payload) {
    return payload.data as T;
  }

  return payload as T;
}

export function fetchPlanningTree(): Promise<PlanningTreeResponse> {
  return requestJson<PlanningTreeResponse>('/api/mig/planning/projects');
}

export function fetchCalendars(): Promise<CalendarListResponse> {
  return requestJson<CalendarListResponse>('/api/mig/planning/calendars');
}

export function fetchManagementOverview(projectId: number): Promise<ManagementOverview> {
  return requestJson<ManagementOverview>(`/api/mig/planning/overview/${projectId}`);
}

export function patchServer(id: number, body: Record<string, unknown>): Promise<PlanningServer> {
  return requestJson<{ server: PlanningServer }>(`/api/mig/planning/servers/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  }).then((data) => data.server);
}

export function createCalendar(body: Record<string, unknown>): Promise<CalendarInfo> {
  return requestJson<{ calendar: CalendarInfo }>('/api/mig/planning/calendars', {
    method: 'POST',
    body: JSON.stringify(body),
  }).then((data) => data.calendar);
}

export function updateCalendar(id: number, body: Record<string, unknown>): Promise<CalendarInfo> {
  return requestJson<{ calendar: CalendarInfo }>(`/api/mig/planning/calendars/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  }).then((data) => data.calendar);
}

export function createSlot(calendarId: number, body: Record<string, unknown>): Promise<CalendarInfo> {
  return requestJson<{ calendar: CalendarInfo }>(`/api/mig/planning/calendars/${calendarId}/slots`, {
    method: 'POST',
    body: JSON.stringify(body),
  }).then((data) => data.calendar);
}

export function updateSlot(slotId: number, body: Record<string, unknown>): Promise<CalendarInfo> {
  return requestJson<{ calendar: CalendarInfo }>(`/api/mig/planning/slots/${slotId}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  }).then((data) => data.calendar);
}

export function deleteSlot(slotId: number): Promise<CalendarInfo> {
  return requestJson<{ calendar: CalendarInfo }>(`/api/mig/planning/slots/${slotId}`, {
    method: 'DELETE',
  }).then((data) => data.calendar);
}

export function createProject(body: Record<string, unknown>): Promise<number> {
  return requestJson<{ id: number | string }>('/api/mig/projects', {
    method: 'POST',
    body: JSON.stringify(body),
  }).then((data) => Number(data.id));
}

export function createWave(projectId: number, body: Record<string, unknown>): Promise<number> {
  return requestJson<{ id: number | string }>(`/api/mig/projects/${projectId}/waves`, {
    method: 'POST',
    body: JSON.stringify(body),
  }).then((data) => Number(data.id));
}

export function createContainer(waveId: number, body: Record<string, unknown>): Promise<number> {
  return requestJson<{ id: number | string }>(`/api/mig/waves/${waveId}/containers`, {
    method: 'POST',
    body: JSON.stringify(body),
  }).then((data) => Number(data.id));
}
