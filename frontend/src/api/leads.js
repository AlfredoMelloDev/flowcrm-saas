import { api } from './client'

export async function fetchLeads(params) {
  const { data } = await api.get('/api/v1/leads', { params })
  return data
}

export async function fetchLead(id) {
  const { data } = await api.get(`/api/v1/leads/${id}`)
  return data.data
}

export async function createLead(payload) {
  const { data } = await api.post('/api/v1/leads', payload)
  return data.data
}

export async function updateLead({ id, payload }) {
  const { data } = await api.patch(`/api/v1/leads/${id}`, payload)
  return data.data
}

export async function deleteLead(id) {
  await api.delete(`/api/v1/leads/${id}`)
}

export async function convertLead({ id, payload }) {
  const { data } = await api.post(`/api/v1/leads/${id}/convert`, payload)
  return data.data
}
