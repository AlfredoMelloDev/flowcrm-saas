import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { LeadFormModal } from './LeadFormModal'

vi.mock('../../api/users')
vi.mock('../../api/auth')
vi.mock('../../api/leads')
import { fetchAssignableUsers } from '../../api/users'
import { updateLead } from '../../api/leads'

describe('LeadFormModal — assignee field visibility by role', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchAssignableUsers.mockResolvedValue([{ id: 'u1', name: 'Bob', email: 'bob@acme.test' }])
  })

  it('shows the "Responsável" field for admin', () => {
    renderWithProviders(<LeadFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    expect(screen.getByLabelText('Responsável')).toBeInTheDocument()
  })

  it('shows the "Responsável" field for manager', () => {
    renderWithProviders(<LeadFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Mia', role: 'manager' },
    })

    expect(screen.getByLabelText('Responsável')).toBeInTheDocument()
  })

  it('hides the "Responsável" field for seller', () => {
    renderWithProviders(<LeadFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Sam', role: 'seller' },
    })

    expect(screen.queryByLabelText('Responsável')).not.toBeInTheDocument()
    // A seller never needs the list at all — the request shouldn't fire.
    expect(fetchAssignableUsers).not.toHaveBeenCalled()
  })
})

describe('LeadFormModal — converted status is never manually editable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchAssignableUsers.mockResolvedValue([])
  })

  it('never offers "Convertido" as an option on a non-converted lead', () => {
    renderWithProviders(
      <LeadFormModal lead={{ id: 'l1', name: 'Jane', status: 'new' }} onClose={() => {}} />,
      { authUser: { id: '1', name: 'Alice', role: 'admin' } },
    )

    expect(screen.queryByRole('option', { name: 'Convertido' })).not.toBeInTheDocument()
  })

  it('locks the status field and shows a message on an already-converted lead', () => {
    renderWithProviders(
      <LeadFormModal lead={{ id: 'l1', name: 'Jane', status: 'converted' }} onClose={() => {}} />,
      { authUser: { id: '1', name: 'Alice', role: 'admin' } },
    )

    expect(screen.queryByLabelText('Status')).not.toBeInTheDocument()
    expect(screen.getByText(/não pode ser alterado/i)).toBeInTheDocument()
  })

  it('does not send "status" when saving an already-converted lead', async () => {
    updateLead.mockResolvedValue({ id: 'l1' })
    const user = userEvent.setup()

    renderWithProviders(
      <LeadFormModal
        lead={{ id: 'l1', name: 'Jane', status: 'converted', notes: null }}
        onClose={() => {}}
      />,
      { authUser: { id: '1', name: 'Alice', role: 'admin' } },
    )

    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() => expect(updateLead).toHaveBeenCalled())
    const [{ payload }] = updateLead.mock.calls[0]
    expect(payload).not.toHaveProperty('status')
  })
})
