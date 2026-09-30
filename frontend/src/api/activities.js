import { api } from './client'

export async function fetchActivities(params) {
  const { data } = await api.get('/api/v1/activities', { params })
  return data
}

export async function fetchActivity(id) {
  const { data } = await api.get(`/api/v1/activities/${id}`)
  return data.data
}

export async function createActivity(payload) {
  const { data } = await api.post('/api/v1/activities', payload)
  return data.data
}

export async function updateActivity({ id, payload }) {
  const { data } = await api.patch(`/api/v1/activities/${id}`, payload)
  return data.data
}

export async function deleteActivity(id) {
  await api.delete(`/api/v1/activities/${id}`)
}

export async function completeActivity(id) {
  const { data } = await api.patch(`/api/v1/activities/${id}/complete`)
  return data.data
}

export async function reopenActivity(id) {
  const { data } = await api.patch(`/api/v1/activities/${id}/reopen`)
  return data.data
}
