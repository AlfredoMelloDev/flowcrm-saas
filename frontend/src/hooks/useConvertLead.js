import { useMutation, useQueryClient } from '@tanstack/react-query'
import { convertLead } from '../api/leads'

export function useConvertLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: convertLead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] })
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}
