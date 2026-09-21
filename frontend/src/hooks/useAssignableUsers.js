import { useQuery } from '@tanstack/react-query'
import { fetchAssignableUsers } from '../api/users'

export function useAssignableUsers(enabled) {
  return useQuery({
    queryKey: ['users', 'assignable'],
    queryFn: fetchAssignableUsers,
    enabled,
  })
}
