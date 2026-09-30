import { useQuery } from '@tanstack/react-query'
import { fetchOpportunityOptions } from '../api/opportunities'

export function useOpportunityOptions() {
  return useQuery({
    queryKey: ['opportunities', 'options'],
    queryFn: fetchOpportunityOptions,
  })
}
