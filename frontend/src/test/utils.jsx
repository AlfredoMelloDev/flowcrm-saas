import { render } from '@testing-library/react'
import { QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { queryClient } from '../api/queryClient'

// Reuses the app's real (singleton) queryClient instead of a fresh one per
// test: the axios interceptor in api/client.js writes to that exact
// instance, so tests exercising the "401 clears auth" flow need to read
// from the same place it writes to. Cleared before each render so tests
// don't leak state into one another.
export function renderWithProviders(ui, { route = '/', authUser } = {}) {
  queryClient.clear()

  // Set *after* clearing but *before* mounting, so components never render
  // a first pass without it (order matters: clearing after seeding would
  // just erase what was seeded).
  if (authUser !== undefined) {
    queryClient.setQueryData(['auth', 'me'], authUser)
  }

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>
    </QueryClientProvider>,
  )
}

export { queryClient }
