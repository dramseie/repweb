export type ProjectRow = {
  id:string; name:string; description?:string|null;
  weatherTrend:1|2|3|4|5; ragOverall:0|1|2|3; progressPct:number; updatedAt:string;
};
export type TaskProgressEntry = {
  id: number;
  taskId: number;
  createdAt: string;
  progressPct: number;
  note: string;
};

export type TaskNode = {
  id:string; projectId:string; parentId?:string|null; wbsCode?:string|null;
  name:string; rag:0|1|2|3; progressPct:number; startDate?:string|null; dueDate?:string|null; sortOrder:number;
  children?:TaskNode[];
  progressLog?: TaskProgressEntry[];
};
export type ProjectDetailDTO = ProjectRow & { tasks: TaskNode[]; };

export type VersionRow = { id:number; label:string; note?:string|null; createdBy?:string|null; createdAt:string; };
export type CompareDTO = {
  projects: { project_id:string; name:string; rag_a:0|1|2|3; rag_b:0|1|2|3; prog_a:number; prog_b:number; wthr_a:1|2|3|4|5; wthr_b:1|2|3|4|5; }[];
  tasks: { project_id:string; task_id:string; wbs_code?:string|null; name:string; rag_a:0|1|2|3; rag_b:0|1|2|3; prog_a:number; prog_b:number; }[];
};

async function j<T>(u:string, init?:RequestInit): Promise<T> {
  const r = await fetch(u, {headers:{'Content-Type':'application/json'}, credentials:'include', ...init});
  if (!r.ok) throw new Error(`${r.status} ${r.statusText}`);
  return r.json();
}
export const PsrApi = {
  listProjects: () => j<ProjectRow[]>('/api/psr/projects'),
  getProject: (id:string) => j<ProjectDetailDTO>(`/api/psr/projects/${id}`),
  upsertProject: (id:string|null, p:Partial<ProjectRow>) =>
    j<ProjectRow>(id?`/api/psr/projects/${id}`:'/api/psr/projects',{method:id?'PUT':'POST',body:JSON.stringify(p)}),
  createTask: (p:Partial<TaskNode>&{projectId:string}) => j<TaskNode>('/api/psr/tasks',{method:'POST',body:JSON.stringify(p)}),
  updateTask: (id:string,p:Partial<TaskNode>)=> j<TaskNode>(`/api/psr/tasks/${id}`,{method:'PUT',body:JSON.stringify(p)}),
  deleteTask: (id:string)=> j<{ok:boolean}>(`/api/psr/tasks/${id}`,{method:'DELETE'}),
  addTaskProgressLog: (id:string, payload:{note:string; progressPct?:number}) =>
    j<TaskProgressEntry>(`/api/psr/tasks/${id}/progress-log`, { method:'POST', body:JSON.stringify(payload) }),
  takeSnapshot: (payload:{label?:string;note?:string}) => j<{ok:true;label:string}>('/api/psr/snapshot',{method:'POST',body:JSON.stringify(payload)}),
  versions: () => j<VersionRow[]>('/api/psr/versions'),
  compare: (a:number,b:number) => j<CompareDTO>(`/api/psr/compare/${a}/${b}`),
};
