import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchActivities } from '../api/activities'

export function useActivities(params) {
  return useQuery({
    queryKey: ['activities', params],
    queryFn: () => fetchActivities(params),
    placeholderData: keepPreviousData,
  })
}
