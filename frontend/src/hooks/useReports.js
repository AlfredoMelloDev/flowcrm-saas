import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchReports } from '../api/reports'

export function useReports(params) {
  return useQuery({
    queryKey: ['reports', params],
    queryFn: () => fetchReports(params),
    placeholderData: keepPreviousData,
  })
}
