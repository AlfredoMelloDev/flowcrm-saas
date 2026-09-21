import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { PublicRoute } from './PublicRoute'

vi.mock('../api/auth')
import { fetchMe } from '../api/auth'

function renderPublic() {
  return renderWithProviders(
    <Routes>
      <Route element={<PublicRoute />}>
        <Route path="/login" element={<div>Login page</div>} />
      </Route>
      <Route path="/dashboard" element={<div>Dashboard content</div>} />
    </Routes>,
    { route: '/login' },
  )
}

describe('PublicRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders the public page when not authenticated', async () => {
    fetchMe.mockRejectedValue({ response: { status: 401 } })

    renderPublic()

    expect(await screen.findByText('Login page')).toBeInTheDocument()
  })

  it('redirects an already-authenticated user away from /login', async () => {
    fetchMe.mockResolvedValue({ id: '1', name: 'Alice', role: 'admin' })

    renderPublic()

    expect(await screen.findByText('Dashboard content')).toBeInTheDocument()
  })
})
