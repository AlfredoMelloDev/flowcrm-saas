import { useMutation, useQueryClient } from '@tanstack/react-query'
import { login } from '../api/auth'

export function useLogin() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: login,
    onSuccess: (user) => {
      queryClient.setQueryData(['auth', 'me'], user)
    },
  })
}
