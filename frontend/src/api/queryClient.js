import { QueryClient } from '@tanstack/react-query'

// A single shared instance: used by <QueryClientProvider> in main.jsx and by
// the axios interceptor in client.js, so a 401 can update the auth cache
// without either module depending on React or the router.
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: false,
    },
  },
})
