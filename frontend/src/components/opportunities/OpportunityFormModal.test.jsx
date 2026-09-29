import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { OpportunityFormModal } from './OpportunityFormModal'

vi.mock('../../api/opportunities')
vi.mock('../../api/clients')
vi.mock('../../api/users')
vi.mock('../../api/auth')
import { createOpportunity, updateOpportunity } from '../../api/opportunities'
import { fetchClientOptions } from '../../api/clients'
import { fetchAssignableUsers } from '../../api/users'

describe('OpportunityFormModal — assignee field visibility by role', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchClientOptions.mockResolvedValue([{ id: 'c1', name: 'Acme Co', document: '123' }])
    fetchAssignableUsers.mockResolvedValue([{ id: 'u1', name: 'Bob', email: 'bob@acme.test' }])
  })

  it('shows the "Responsável" field for admin', () => {
    renderWithProviders(<OpportunityFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    expect(screen.getByLabelText('Responsável')).toBeInTheDocument()
  })

  it('shows the "Responsável" field for manager', () => {
    renderWithProviders(<OpportunityFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Mia', role: 'manager' },
    })

    expect(screen.getByLabelText('Responsável')).toBeInTheDocument()
  })

  it('hides the "Responsável" field for seller', () => {
    renderWithProviders(<OpportunityFormModal onClose={() => {}} />, {
      authUser: { id: '1', name: 'Sam', role: 'seller' },
    })

    expect(screen.queryByLabelText('Responsável')).not.toBeInTheDocument()
    expect(fetchAssignableUsers).not.toHaveBeenCalled()
  })
})

describe('OpportunityFormModal — create and edit', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchClientOptions.mockResolvedValue([{ id: 'c1', name: 'Acme Co', document: '123' }])
    fetchAssignableUsers.mockResolvedValue([{ id: 'u1', name: 'Bob', email: 'bob@acme.test' }])
  })

  it('creates an opportunity with the filled fields, and never sends "stage"', async () => {
    createOpportunity.mockResolvedValue({ id: 'o1' })
    const onClose = vi.fn()
    const user = userEvent.setup()

    renderWithProviders(<OpportunityFormModal onClose={onClose} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    await user.type(screen.getByLabelText('Título'), 'Big Deal')
    await screen.findByRole('option', { name: 'Acme Co' })
    await user.selectOptions(screen.getByLabelText('Cliente'), 'c1')
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(createOpportunity).toHaveBeenCalledWith(
        {
          title: 'Big Deal',
          client_id: 'c1',
          value: null,
          expected_close_date: null,
          notes: null,
          user_id: null,
        },
        expect.anything(),
      ),
    )
    await waitFor(() => expect(onClose).toHaveBeenCalled())
  })

  it('edits an opportunity without touching stage or lost_reason', async () => {
    updateOpportunity.mockResolvedValue({ id: 'o1' })
    const onClose = vi.fn()
    const user = userEvent.setup()

    const opportunity = {
      id: 'o1',
      title: 'Old title',
      stage: 'contacted',
      value: '500.00',
      expected_close_date: null,
      lost_reason: null,
      notes: null,
      client: { id: 'c1', name: 'Acme Co' },
      assigned_to: null,
    }

    renderWithProviders(<OpportunityFormModal opportunity={opportunity} onClose={onClose} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    const titleInput = screen.getByLabelText('Título')
    await user.clear(titleInput)
    await user.type(titleInput, 'New title')
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(updateOpportunity).toHaveBeenCalledWith(
        {
          id: 'o1',
          payload: {
            title: 'New title',
            client_id: 'c1',
            value: '500.00',
            expected_close_date: null,
            notes: null,
            user_id: null,
          },
        },
        expect.anything(),
      ),
    )
  })

  it('shows and re-submits a date-only value even though the API returns a full ISO datetime', async () => {
    updateOpportunity.mockResolvedValue({ id: 'o1' })
    const user = userEvent.setup()

    const opportunity = {
      id: 'o1',
      title: 'Renewal',
      stage: 'new',
      value: null,
      expected_close_date: '2026-12-25T00:00:00.000000Z',
      lost_reason: null,
      notes: null,
      client: { id: 'c1', name: 'Acme Co' },
      assigned_to: null,
    }

    renderWithProviders(<OpportunityFormModal opportunity={opportunity} onClose={() => {}} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    expect(screen.getByLabelText('Previsão de fechamento')).toHaveValue('2026-12-25')

    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(updateOpportunity).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'o1',
          payload: expect.objectContaining({ expected_close_date: '2026-12-25' }),
        }),
        expect.anything(),
      ),
    )
  })

  it('keeps the current client selected and submits its id when the client is no longer active', async () => {
    // /clients/options only returns active clients — this opportunity's
    // client isn't in that list, simulating one that was deactivated after
    // the opportunity was created.
    fetchClientOptions.mockResolvedValue([])
    updateOpportunity.mockResolvedValue({ id: 'o1' })
    const user = userEvent.setup()

    const opportunity = {
      id: 'o1',
      title: 'Renewal',
      stage: 'new',
      value: null,
      expected_close_date: null,
      lost_reason: null,
      notes: null,
      client: { id: 'c9', name: 'Inactive Co' },
      assigned_to: null,
    }

    renderWithProviders(<OpportunityFormModal opportunity={opportunity} onClose={() => {}} />, {
      authUser: { id: '1', name: 'Alice', role: 'admin' },
    })

    expect(await screen.findByRole('option', { name: /Inactive Co/ })).toBeInTheDocument()
    expect(screen.getByLabelText('Cliente')).toHaveValue('c9')

    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(updateOpportunity).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'o1',
          payload: expect.objectContaining({ client_id: 'c9' }),
        }),
        expect.anything(),
      ),
    )
  })
})
