import axios from 'axios';

const API = '/api/cmdb/modeler';
const TENANT = 'cmdb';

export const getTypes     = () => axios.get(`${API}/types`,           { params:{ tenant:TENANT }}).then(r=>r.data);
export const getRelTypes  = () => axios.get(`${API}/relations/types`, { params:{ tenant:TENANT }}).then(r=>r.data);
export const getGraph     = (params={}) => axios.get(`${API}/graph`,  { params:{ tenant:TENANT, ...params }}).then(r=>r.data);

export const createNode   = (type, payload={}) => axios.post(`${API}/node`, { type, ...payload }, { params:{ tenant:TENANT }}).then(r=>r.data);
export const updateNode   = (ciOrId, payload) => axios.patch(`${API}/node/${encodeURIComponent(ciOrId)}`, payload, { params:{ tenant:TENANT }}).then(r=>r.data);
export const deleteNode   = (ciOrId)          => axios.delete(`${API}/node/${encodeURIComponent(ciOrId)}`, { params:{ tenant:TENANT }}).then(r=>r.data);

export const createEdge   = (source, target, type) => axios.post(`${API}/edge`, { source, target, type }, { params:{ tenant:TENANT }}).then(r=>r.data);
export const deleteEdge   = (id)                  => axios.delete(`${API}/edge/${id}`, { params:{ tenant:TENANT }}).then(r=>r.data);

export const saveLayout   = (name, nodes) => axios.post(`${API}/layout/save`, { name, nodes }, { params:{ tenant:TENANT }}).then(r=>r.data);

export const getNodeAttributes = (ci) =>
  axios.get(`${API}/node/${encodeURIComponent(ci)}/attributes`, { params:{ tenant:TENANT }}).then(r=>r.data);

export const getGraphEgo = (ci, depth=1) =>
  axios.get(`${API}/graph/ego`, { params:{ tenant:TENANT, ci, depth }}).then(r=>r.data);
