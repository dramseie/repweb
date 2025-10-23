// assets/frw/lib/api.js
export default {
  async getTemplate(code){ const r = await fetch(`/api/frw/templates/${code}`); return r.json(); },
  async createRun(templateCode){ const r = await fetch('/api/frw/runs', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({templateCode})}); return r.json(); },
  async getRun(id){ const r= await fetch(`/api/frw/runs/${id}`); return r.json(); },
  async patchRun(id, {answers}){ const r= await fetch(`/api/frw/runs/${id}`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body: JSON.stringify({answers})}); return r.json(); },
  async priceRun(id){ const r= await fetch(`/api/frw/runs/${id}/price`, {method:'POST'}); return r.json(); },
  async submitRun(id){ const r= await fetch(`/api/frw/runs/${id}/submit`, {method:'POST'}); return r.json(); },
  async lookup(type, q){ const r = await fetch(`/api/frw/lookups?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q||'')}`); return r.json(); },
};