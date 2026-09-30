import { useMutation, useQueryClient } from '@tanstack/react-query'
import { reopenActivity } from '../api/activities'

export function useReopenActivity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: reopenActivity,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['activities'] })
    },
  })
}
