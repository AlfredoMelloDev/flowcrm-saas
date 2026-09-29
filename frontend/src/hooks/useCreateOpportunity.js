import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createOpportunity } from '../api/opportunities'

export function useCreateOpportunity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createOpportunity,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}
