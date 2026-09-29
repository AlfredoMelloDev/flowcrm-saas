import { useMutation, useQueryClient } from '@tanstack/react-query'
import { updateOpportunity } from '../api/opportunities'

// Dedicated mutation for the pipeline card's stage <Select>: unlike
// useUpdateOpportunity (used by the edit modal, where a brief pending state
// on the submit button is fine), a stage change should move the card between
// columns immediately. Every cached ['opportunities', 'pipeline', ...] query
// is patched directly (regardless of its current search/user_id params) so
// the card moves in whichever filtered view is on screen, with a snapshot
// kept for rollback if the request fails.
export function useUpdateOpportunityStage() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: updateOpportunity,
    onMutate: async ({ id, payload }) => {
      await queryClient.cancelQueries({ queryKey: ['opportunities', 'pipeline'] })

      const previousQueries = queryClient.getQueriesData({ queryKey: ['opportunities', 'pipeline'] })

      queryClient.setQueriesData({ queryKey: ['opportunities', 'pipeline'] }, (old) => {
        if (!old) {
          return old
        }
        return old.map((opportunity) =>
          opportunity.id === id ? { ...opportunity, ...payload } : opportunity,
        )
      })

      return { previousQueries }
    },
    onError: (error, variables, context) => {
      context?.previousQueries?.forEach(([queryKey, data]) => {
        queryClient.setQueryData(queryKey, data)
      })
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['opportunities'] })
    },
  })
}
