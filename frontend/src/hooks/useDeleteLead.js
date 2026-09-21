import { useMutation, useQueryClient } from '@tanstack/react-query'
import { deleteLead } from '../api/leads'

export function useDeleteLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteLead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
    },
  })
}
