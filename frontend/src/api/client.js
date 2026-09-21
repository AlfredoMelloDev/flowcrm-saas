import axios from 'axios'
import { queryClient } from './queryClient'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

// A 401 means the session is gone (never logged in, expired, or the account
// was deactivated mid-session — see EnsureAccountIsActive on the backend).
// We only ever update the cached auth state here; setQueryData (not
// invalidateQueries) so this never triggers a refetch of /me — which would
// 401 again and call this same handler, i.e. no loop by construction.
// Redirecting is ProtectedRoute's job, not this module's — it reads the same
// query and reacts to the cache change on its own. Exported (not inlined)
// so it can be unit tested without mocking the whole HTTP layer.
export function handleResponseError(error) {
  if (error.response?.status === 401) {
    queryClient.setQueryData(['auth', 'me'], null)
  }

  return Promise.reject(error)
}

api.interceptors.response.use((response) => response, handleResponseError)
