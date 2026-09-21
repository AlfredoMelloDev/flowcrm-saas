import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchLeads } from '../api/leads'

export function useLeads(params) {
  return useQuery({
    queryKey: ['leads', params],
    queryFn: () => fetchLeads(params),
    placeholderData: keepPreviousData,
  })
}
