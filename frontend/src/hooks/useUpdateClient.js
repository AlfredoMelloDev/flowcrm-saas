import { useMutation, useQueryClient } from '@tanstack/react-query'
import { updateClient } from '../api/clients'

export function useUpdateClient() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: updateClient,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
    },
  })
}
