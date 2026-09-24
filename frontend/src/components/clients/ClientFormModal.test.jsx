import { screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { ClientFormModal } from './ClientFormModal'

vi.mock('../../api/users')
vi.mock('../../api/auth')
import { fetchAssignableUsers } from '../../api/users'

describe('ClientFormModal — assignee field visibility by role', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchAssignableUsers.mockResolvedValue([{ id: 'u1', name: 'Bob', email: 'bob@acme.test' }])
  })

  it('shows the "Responsável" field for admin', () => {
    renderWithProviders(<ClientFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    expect(screen.getByLabelText('Responsável')).toBeInTheDocument()
  })

  it('shows the "Responsável" field for manager', () => {
    renderWithProviders(<ClientFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Mia', role: 'manager' },
    })

    expect(screen.getByLabelText('Responsável')).toBeInTheDocument()
  })

  it('hides the "Responsável" field for seller', () => {
    renderWithProviders(<ClientFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Sam', role: 'seller' },
    })

    expect(screen.queryByLabelText('Responsável')).not.toBeInTheDocument()
    // A seller never needs the list at all — the request shouldn't fire.
    expect(fetchAssignableUsers).not.toHaveBeenCalled()
  })
})
