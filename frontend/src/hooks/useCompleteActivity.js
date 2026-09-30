import { useMutation, useQueryClient } from '@tanstack/react-query'
import { completeActivity } from '../api/activities'

export function useCompleteActivity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: completeActivity,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['activities'] })
    },
  })
}
