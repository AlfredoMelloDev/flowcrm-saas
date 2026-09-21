import { api } from './client'

export async function fetchAssignableUsers() {
  const { data } = await api.get('/api/v1/users/assignable')
  return data.data
}
