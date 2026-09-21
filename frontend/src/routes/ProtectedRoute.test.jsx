import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { ProtectedRoute } from './ProtectedRoute'

vi.mock('../api/auth')
import { fetchMe } from '../api/auth'

function renderProtected() {
  return renderWithProviders(
    <Routes>
      <Route element={<ProtectedRoute />}>
        <Route path="/dashboard" element={<div>Dashboard content</div>} />
      </Route>
      <Route path="/login" element={<div>Login page</div>} />
    </Routes>,
    { route: '/dashboard' },
  )
}

describe('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('redirects to /login when not authenticated', async () => {
    fetchMe.mockRejectedValue({ response: { status: 401 } })

    renderProtected()

    expect(await screen.findByText('Login page')).toBeInTheDocument()
  })

  it('renders the protected content when authenticated', async () => {
    fetchMe.mockResolvedValue({ id: '1', name: 'Alice', role: 'admin' })

    renderProtected()

    expect(await screen.findByText('Dashboard content')).toBeInTheDocument()
  })
})
