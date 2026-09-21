import { useMutation, useQueryClient } from '@tanstack/react-query'
import { register } from '../api/auth'

export function useRegister() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: register,
    onSuccess: (user) => {
      queryClient.setQueryData(['auth', 'me'], user)
    },
  })
}
