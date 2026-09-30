import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { ActivitiesPage } from './ActivitiesPage'

vi.mock('../api/activities')
vi.mock('../api/leads')
vi.mock('../api/clients')
vi.mock('../api/opportunities')
vi.mock('../api/users')
vi.mock('../api/auth')
import {
  fetchActivities,
  deleteActivity,
  completeActivity,
  reopenActivity,
} from '../api/activities'
import { fetchLeadOptions } from '../api/leads'
import { fetchClientOptions } from '../api/clients'
import { fetchOpportunityOptions } from '../api/opportunities'
import { fetchAssignableUsers } from '../api/users'

function activity(overrides) {
  return {
    id: 'a1',
    title: 'Call the client',
    type: 'call',
    status: 'pending',
    is_overdue: false,
    scheduled_at: '2026-03-10T14:30:00.000000Z',
    assigned_to: { id: 'u1', name: 'Alice', email: 'alice@acme.test' },
    lead: null,
    client: null,
    opportunity: null,
    ...overrides,
  }
}

describe('ActivitiesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchLeadOptions.mockResolvedValue([])
    fetchClientOptions.mockResolvedValue([])
    fetchOpportunityOptions.mockResolvedValue([])
    fetchAssignableUsers.mockResolvedValue([])
  })

  it('shows a loading spinner while activities are loading', () => {
    fetchActivities.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action when activities fail to load', async () => {
    fetchActivities.mockRejectedValue(new Error('network error'))

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })

    expect(await screen.findByText(/não foi possível carregar/i)).toBeInTheDocument()
  })

  it('shows an empty state when there are no activities', async () => {
    fetchActivities.mockResolvedValue({ data: [], meta: {} })

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })

    expect(await screen.findByText('Nenhuma atividade ainda')).toBeInTheDocument()
  })

  it('renders an activity row once loaded', async () => {
    fetchActivities.mockResolvedValue({ data: [activity()], meta: {} })

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })

    expect(await screen.findAllByText('Call the client')).not.toHaveLength(0)
  })

  it('re-fetches with the typed search term after debouncing', async () => {
    fetchActivities.mockResolvedValue({ data: [], meta: {} })
    const user = userEvent.setup()

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })

    await screen.findByText('Nenhuma atividade ainda')
    await user.type(screen.getByLabelText('Buscar'), 'client call')

    await waitFor(() =>
      expect(fetchActivities).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'client call' }),
      ),
    )
  })

  it('re-fetches with the "overdue" window when the "Atrasadas" tab is clicked', async () => {
    fetchActivities.mockResolvedValue({ data: [], meta: {} })
    const user = userEvent.setup()

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })

    await screen.findByText('Nenhuma atividade ainda')
    await user.click(screen.getByRole('button', { name: 'Atrasadas' }))

    await waitFor(() =>
      expect(fetchActivities).toHaveBeenCalledWith(expect.objectContaining({ window: 'overdue' })),
    )
  })

  it('hides Excluir for seller', async () => {
    fetchActivities.mockResolvedValue({ data: [activity()], meta: {} })

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u2', name: 'Sam', role: 'seller' } })
    await screen.findAllByText('Call the client')

    expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument()
  })

  it('shows Excluir for admin and calls deleteActivity on confirm', async () => {
    fetchActivities.mockResolvedValue({ data: [activity()], meta: {} })
    deleteActivity.mockResolvedValue()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const user = userEvent.setup()

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })
    await screen.findAllByText('Call the client')
    await user.click(screen.getAllByRole('button', { name: 'Excluir' })[0])

    await waitFor(() => expect(deleteActivity).toHaveBeenCalledWith('a1', expect.anything()))
  })

  it('does not delete when the confirm dialog is dismissed', async () => {
    fetchActivities.mockResolvedValue({ data: [activity()], meta: {} })
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const user = userEvent.setup()

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })
    await screen.findAllByText('Call the client')
    await user.click(screen.getAllByRole('button', { name: 'Excluir' })[0])

    expect(deleteActivity).not.toHaveBeenCalled()
  })

  it('calls completeActivity when "Concluir" is clicked on a pending activity', async () => {
    fetchActivities.mockResolvedValue({ data: [activity({ status: 'pending' })], meta: {} })
    completeActivity.mockResolvedValue(activity({ status: 'completed' }))
    const user = userEvent.setup()

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })
    await screen.findAllByText('Call the client')
    await user.click(screen.getAllByRole('button', { name: 'Concluir' })[0])

    await waitFor(() => expect(completeActivity).toHaveBeenCalledWith('a1', expect.anything()))
  })

  it('calls reopenActivity when "Reabrir" is clicked on a completed activity', async () => {
    fetchActivities.mockResolvedValue({ data: [activity({ status: 'completed' })], meta: {} })
    reopenActivity.mockResolvedValue(activity({ status: 'pending' }))
    const user = userEvent.setup()

    renderWithProviders(<ActivitiesPage />, { authUser: { id: 'u1', name: 'Alice', role: 'admin' } })
    await screen.findAllByText('Call the client')
    await user.click(screen.getAllByRole('button', { name: 'Reabrir' })[0])

    await waitFor(() => expect(reopenActivity).toHaveBeenCalledWith('a1', expect.anything()))
  })
})
