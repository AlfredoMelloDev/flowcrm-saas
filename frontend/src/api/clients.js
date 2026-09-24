import { api } from './client'

export async function fetchClients(params) {
  const { data } = await api.get('/api/v1/clients', { params })
  return data
}

export async function fetchClient(id) {
  const { data } = await api.get(`/api/v1/clients/${id}`)
  return data.data
}

export async function createClient(payload) {
  const { data } = await api.post('/api/v1/clients', payload)
  return data.data
}

export async function updateClient({ id, payload }) {
  const { data } = await api.patch(`/api/v1/clients/${id}`, payload)
  return data.data
}

export async function deleteClient(id) {
  await api.delete(`/api/v1/clients/${id}`)
}
