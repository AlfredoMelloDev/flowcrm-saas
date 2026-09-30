import { fireEvent, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { ActivityFormModal } from './ActivityFormModal'

vi.mock('../../api/activities')
vi.mock('../../api/leads')
vi.mock('../../api/clients')
vi.mock('../../api/opportunities')
vi.mock('../../api/users')
vi.mock('../../api/auth')
import { createActivity, updateActivity } from '../../api/activities'
import { fetchLeadOptions } from '../../api/leads'
import { fetchClientOptions } from '../../api/clients'
import { fetchOpportunityOptions } from '../../api/opportunities'
import { fetchAssignableUsers } from '../../api/users'

describe('ActivityFormModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchLeadOptions.mockResolvedValue([{ id: 'l1', name: 'John Doe' }])
    fetchClientOptions.mockResolvedValue([{ id: 'c1', name: 'Acme Co' }])
    fetchOpportunityOptions.mockResolvedValue([{ id: 'o1', title: 'Big Deal' }])
    fetchAssignableUsers.mockResolvedValue([{ id: 'u1', name: 'Alice', email: 'alice@acme.test' }])
  })

  it('creates an activity with no relation by default, defaulting the assignee to the current user', async () => {
    createActivity.mockResolvedValue({ id: 'a1' })
    const onClose = vi.fn()
    const user = userEvent.setup()

    renderWithProviders(<ActivityFormModal onClose={onClose} />, {
      authUser: { id: 'u1', name: 'Alice', role: 'admin' },
    })

    await user.type(screen.getByLabelText('Título'), 'Call the client')
    fireEvent.change(screen.getByLabelText('Agendado para'), { target: { value: '2026-03-10T10:00' } })
    await screen.findByRole('option', { name: 'Alice' })
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(createActivity).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Call the client',
          type: 'task',
          description: null,
          lead_id: null,
          client_id: null,
          opportunity_id: null,
          user_id: 'u1',
        }),
        expect.anything(),
      ),
    )
    await waitFor(() => expect(onClose).toHaveBeenCalled())
  })

  it('sends lead_id and nulls client_id/opportunity_id when "Lead" is chosen as the relation', async () => {
    createActivity.mockResolvedValue({ id: 'a1' })
    const user = userEvent.setup()

    renderWithProviders(<ActivityFormModal onClose={() => {}} />, {
      authUser: { id: 'u1', name: 'Alice', role: 'admin' },
    })

    await user.type(screen.getByLabelText('Título'), 'Follow up')
    fireEvent.change(screen.getByLabelText('Agendado para'), { target: { value: '2026-03-10T10:00' } })
    await user.selectOptions(screen.getByLabelText('Relacionado a'), 'lead')
    await screen.findByRole('option', { name: 'John Doe' })
    await user.selectOptions(screen.getByLabelText('Lead'), 'l1')
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(createActivity).toHaveBeenCalledWith(
        expect.objectContaining({ lead_id: 'l1', client_id: null, opportunity_id: null }),
        expect.anything(),
      ),
    )
  })

  it('switching the relation type clears the previously selected related record', async () => {
    const user = userEvent.setup()

    renderWithProviders(<ActivityFormModal onClose={() => {}} />, {
      authUser: { id: 'u1', name: 'Alice', role: 'admin' },
    })

    await user.selectOptions(screen.getByLabelText('Relacionado a'), 'lead')
    await screen.findByRole('option', { name: 'John Doe' })
    await user.selectOptions(screen.getByLabelText('Lead'), 'l1')
    expect(screen.getByLabelText('Lead')).toHaveValue('l1')

    await user.selectOptions(screen.getByLabelText('Relacionado a'), 'client')
    await screen.findByRole('option', { name: 'Acme Co' })
    expect(screen.getByLabelText('Cliente')).toHaveValue('')
  })

  it('does not include user_id in the payload for a seller (no "Responsável" field)', async () => {
    createActivity.mockResolvedValue({ id: 'a1' })
    const user = userEvent.setup()

    renderWithProviders(<ActivityFormModal onClose={() => {}} />, {
      authUser: { id: 'u2', name: 'Sam', role: 'seller' },
    })

    expect(screen.queryByLabelText('Responsável')).not.toBeInTheDocument()
    expect(fetchAssignableUsers).not.toHaveBeenCalled()

    await user.type(screen.getByLabelText('Título'), 'Task for myself')
    fireEvent.change(screen.getByLabelText('Agendado para'), { target: { value: '2026-03-10T10:00' } })
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(createActivity).toHaveBeenCalledWith(
        expect.not.objectContaining({ user_id: expect.anything() }),
        expect.anything(),
      ),
    )
  })

  it('round-trips an existing scheduled_at through the datetime-local input without changing the instant', async () => {
    updateActivity.mockResolvedValue({ id: 'a1' })
    const user = userEvent.setup()

    const activity = {
      id: 'a1',
      title: 'Existing call',
      type: 'call',
      description: null,
      scheduled_at: '2026-03-10T14:30:00.000000Z',
      lead: null,
      client: null,
      opportunity: null,
      assigned_to: { id: 'u1', name: 'Alice' },
    }

    renderWithProviders(<ActivityFormModal activity={activity} onClose={() => {}} />, {
      authUser: { id: 'u1', name: 'Alice', role: 'admin' },
    })

    await screen.findByRole('option', { name: 'Alice' })
    await user.click(screen.getByRole('button', { name: 'Salvar' }))

    await waitFor(() =>
      expect(updateActivity).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'a1',
          payload: expect.objectContaining({ scheduled_at: '2026-03-10T14:30:00.000Z' }),
        }),
        expect.anything(),
      ),
    )
  })

  it('pre-fills the relation fields from an activity related to an opportunity', async () => {
    const activity = {
      id: 'a1',
      title: 'Negotiate terms',
      type: 'meeting',
      description: null,
      scheduled_at: null,
      lead: null,
      client: null,
      opportunity: { id: 'o1', title: 'Big Deal' },
      assigned_to: { id: 'u1', name: 'Alice' },
    }

    renderWithProviders(<ActivityFormModal activity={activity} onClose={() => {}} />, {
      authUser: { id: 'u1', name: 'Alice', role: 'admin' },
    })

    expect(screen.getByLabelText('Relacionado a')).toHaveValue('opportunity')
    expect(await screen.findByLabelText('Oportunidade')).toHaveValue('o1')
  })
})
