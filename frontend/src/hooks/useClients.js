import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchClients } from '../api/clients'

export function useClients(params) {
  return useQuery({
    queryKey: ['clients', params],
    queryFn: () => fetchClients(params),
    placeholderData: keepPreviousData,
  })
}
