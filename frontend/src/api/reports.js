import { api } from './client'

export async function fetchReports(params) {
  const { data } = await api.get('/api/v1/reports', { params })
  return data.data
}
