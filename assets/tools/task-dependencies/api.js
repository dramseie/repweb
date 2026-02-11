import axios from 'axios';

const API = '/api/smartsheet/task-dependencies';

export const getTasks = () => axios.get(`${API}/tasks`).then((r) => r.data);

export const getWorkspaces = () => axios.get(`${API}/workspaces`).then((r) => r.data);

export const createWorkspace = (payload = {}) =>
  axios.post(`${API}/workspaces`, payload).then((r) => r.data);

export const getGraph = (workspaceId) =>
  axios.get(`${API}/graph`, { params: { workspaceId } }).then((r) => r.data);

export const createNode = (payload) =>
  axios.post(`${API}/node`, payload).then((r) => r.data);

export const updateNode = (id, payload) =>
  axios.patch(`${API}/node/${id}`, payload).then((r) => r.data);

export const deleteNode = (id) =>
  axios.delete(`${API}/node/${id}`).then((r) => r.data);

export const createEdge = (payload) =>
  axios.post(`${API}/edge`, payload).then((r) => r.data);

export const deleteEdge = (id) =>
  axios.delete(`${API}/edge/${id}`).then((r) => r.data);

export const updateEdge = (id, payload) =>
  axios.patch(`${API}/edge/${id}`, payload).then((r) => r.data);

export const saveLayout = (workspaceId, nodes) =>
  axios.post(`${API}/layout/save`, { workspaceId, nodes }).then((r) => r.data);
