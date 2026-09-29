import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchOpportunityPipeline } from '../api/opportunities'

export function useOpportunityPipeline(params) {
  return useQuery({
    queryKey: ['opportunities', 'pipeline', params],
    queryFn: () => fetchOpportunityPipeline(params),
    placeholderData: keepPreviousData,
  })
}
