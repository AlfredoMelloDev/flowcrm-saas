import { api } from './client'

async function ensureCsrfCookie() {
  await api.get('/sanctum/csrf-cookie')
}

export async function login({ email, password }) {
  await ensureCsrfCookie()
  const { data } = await api.post('/api/v1/auth/login', { email, password })
  return data.data
}

export async function register({ company, user }) {
  await ensureCsrfCookie()
  const { data } = await api.post('/api/v1/auth/register', { company, user })
  return data.data
}

export async function logout() {
  await api.post('/api/v1/auth/logout')
}

export async function fetchMe() {
  const { data } = await api.get('/api/v1/auth/me')
  return data.data
}
