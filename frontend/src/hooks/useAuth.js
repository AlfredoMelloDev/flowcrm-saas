import { useQuery } from '@tanstack/react-query'
import { fetchMe } from '../api/auth'

export function useAuth() {
  const {
    data: user,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: fetchMe,
    retry: false,
  })

  return {
    user: user ?? null,
    isAuthenticated: Boolean(user),
    isLoading,
    isError,
    role: user?.role ?? null,
  }
}
