import { useMutation, useQueryClient } from '@tanstack/react-query'
import { updateLead } from '../api/leads'

export function useUpdateLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: updateLead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
    },
  })
}
