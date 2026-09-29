import { useMutation, useQueryClient } from '@tanstack/react-query'
import { deleteOpportunity } from '../api/opportunities'

export function useDeleteOpportunity() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteOpportunity,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}
