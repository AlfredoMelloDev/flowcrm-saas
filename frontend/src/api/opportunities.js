import { api } from './client'

export async function fetchOpportunityPipeline(params) {
  const { data } = await api.get('/api/v1/opportunities/pipeline', { params })
  return data.data
}

export async function createOpportunity(payload) {
  const { data } = await api.post('/api/v1/opportunities', payload)
  return data.data
}

export async function updateOpportunity({ id, payload }) {
  const { data } = await api.patch(`/api/v1/opportunities/${id}`, payload)
  return data.data
}

export async function deleteOpportunity(id) {
  await api.delete(`/api/v1/opportunities/${id}`)
}

export async function fetchOpportunityOptions() {
  const { data } = await api.get('/api/v1/opportunities/options')
  return data.data
}
