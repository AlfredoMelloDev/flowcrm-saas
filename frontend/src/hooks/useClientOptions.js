import { useQuery } from '@tanstack/react-query'
import { fetchClientOptions } from '../api/clients'

export function useClientOptions() {
  return useQuery({
    queryKey: ['clients', 'options'],
    queryFn: fetchClientOptions,
  })
}
