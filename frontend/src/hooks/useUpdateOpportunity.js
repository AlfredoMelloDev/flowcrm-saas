import { useMutation, useQueryClient } from '@tanstack/react-query'
import { updateOpportunity } from '../api/opportunities'

export function useUpdateOpportunity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: updateOpportunity,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}
