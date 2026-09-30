import { useQuery } from '@tanstack/react-query'
import { fetchLeadOptions } from '../api/leads'

export function useLeadOptions() {
  return useQuery({
    queryKey: ['leads', 'options'],
    queryFn: fetchLeadOptions,
  })
}
