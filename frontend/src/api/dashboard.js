import { api } from './client'

export async function fetchDashboard() {
  const { data } = await api.get('/api/v1/dashboard')
  return data.data
}
