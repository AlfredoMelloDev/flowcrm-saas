import { act, screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { ProtectedRoute } from './ProtectedRoute'
import { handleResponseError } from '../api/client'

vi.mock('../api/auth')
import { fetchMe } from '../api/auth'

describe('session expiry while on a protected route', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('a 401 from any protected resource flips auth state and sends the user to /login', async () => {
    fetchMe.mockResolvedValue({ id: '1', name: 'Alice', role: 'admin' })

    renderWithProviders(
      <Routes>
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={<div>Dashboard content</div>} />
        </Route>
        <Route path="/login" element={<div>Login page</div>} />
      </Routes>,
      { route: '/dashboard' },
    )

    // Still authenticated, on the dashboard.
    expect(await screen.findByText('Dashboard content')).toBeInTheDocument()

    // Simulate a 401 returned by some other protected call (e.g. GET
    // /api/v1/leads) going through the same axios response interceptor —
    // this is what actually happens in the app when a session dies mid-way.
    await act(async () => {
      await handleResponseError({ response: { status: 401 } }).catch(() => {})
    })

    expect(await screen.findByText('Login page')).toBeInTheDocument()
  })
})
