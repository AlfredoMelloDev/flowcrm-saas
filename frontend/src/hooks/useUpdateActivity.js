import { useMutation, useQueryClient } from '@tanstack/react-query'
import { updateActivity } from '../api/activities'

export function useUpdateActivity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: updateActivity,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['activities'] })
    },
  })
}
