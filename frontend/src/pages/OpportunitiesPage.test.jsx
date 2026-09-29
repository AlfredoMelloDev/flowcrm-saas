import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { OpportunitiesPage } from './OpportunitiesPage'

vi.mock('../api/opportunities')
vi.mock('../api/clients')
vi.mock('../api/users')
vi.mock('../api/auth')
import { fetchOpportunityPipeline, updateOpportunity, deleteOpportunity } from '../api/opportunities'
import { fetchClientOptions } from '../api/clients'
import { fetchAssignableUsers } from '../api/users'

const STAGE_LABELS = ['Novo', 'Contatado', 'Proposta', 'Negociação', 'Ganho', 'Perdido']

function opportunity(overrides) {
  return {
    id: 'o1',
    title: 'New Deal',
    stage: 'new',
    value: '100.00',
    expected_close_date: null,
    lost_reason: null,
    notes: null,
    client: { id: 'c1', name: 'Acme' },
    assigned_to: null,
    ...overrides,
  }
}

describe('OpportunitiesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchClientOptions.mockResolvedValue([])
    fetchAssignableUsers.mockResolvedValue([])
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders all 6 pipeline columns', async () => {
    fetchOpportunityPipeline.mockResolvedValue([])

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    for (const label of STAGE_LABELS) {
      expect(await screen.findByText(label)).toBeInTheDocument()
    }
  })

  it('shows a loading spinner while the pipeline is loading', () => {
    fetchOpportunityPipeline.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action when the pipeline fails to load', async () => {
    fetchOpportunityPipeline.mockRejectedValue(new Error('network error'))

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(await screen.findByText(/não foi possível carregar/i)).toBeInTheDocument()
  })

  it('shows an empty message in every column when the pipeline has no opportunities', async () => {
    fetchOpportunityPipeline.mockResolvedValue([])

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(await screen.findAllByText('Nenhuma oportunidade')).toHaveLength(6)
  })

  it('places each opportunity in its matching stage column', async () => {
    fetchOpportunityPipeline.mockResolvedValue([
      opportunity({ id: 'o1', title: 'New Deal', stage: 'new' }),
      opportunity({ id: 'o2', title: 'Contacted Deal', stage: 'contacted' }),
    ])

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    const newColumn = await screen.findByTestId('opportunity-column-new')
    const contactedColumn = screen.getByTestId('opportunity-column-contacted')

    expect(within(newColumn).getByText('New Deal')).toBeInTheDocument()
    expect(within(contactedColumn).getByText('Contacted Deal')).toBeInTheDocument()
    expect(within(newColumn).queryByText('Contacted Deal')).not.toBeInTheDocument()
  })

  it('re-fetches the pipeline with the typed search term', async () => {
    fetchOpportunityPipeline.mockResolvedValue([])
    const user = userEvent.setup()

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    await screen.findAllByText('Nenhuma oportunidade')
    await user.type(screen.getByLabelText('Buscar'), 'acme')

    await waitFor(() =>
      expect(fetchOpportunityPipeline).toHaveBeenCalledWith({ search: 'acme', user_id: undefined }),
    )
  })

  it('shows the "Responsável" filter for admin', async () => {
    fetchOpportunityPipeline.mockResolvedValue([])

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(await screen.findByLabelText('Responsável')).toBeInTheDocument()
  })

  it('hides the "Responsável" filter for seller and never fetches assignable users', async () => {
    fetchOpportunityPipeline.mockResolvedValue([])

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '2', name: 'Sam', role: 'seller' } })
    await screen.findAllByText('Nenhuma oportunidade')

    expect(screen.queryByLabelText('Responsável')).not.toBeInTheDocument()
    expect(fetchAssignableUsers).not.toHaveBeenCalled()
  })

  it('hides Excluir for seller', async () => {
    fetchOpportunityPipeline.mockResolvedValue([opportunity()])

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '2', name: 'Sam', role: 'seller' } })
    await screen.findByText('New Deal')

    expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument()
  })

  it('shows Excluir for admin and calls deleteOpportunity on confirm', async () => {
    fetchOpportunityPipeline.mockResolvedValue([opportunity()])
    deleteOpportunity.mockResolvedValue()
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const user = userEvent.setup()

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })
    await screen.findByText('New Deal')
    await user.click(screen.getByRole('button', { name: 'Excluir' }))

    await waitFor(() => expect(deleteOpportunity).toHaveBeenCalledWith('o1', expect.anything()))
  })

  it('optimistically moves the card to the new column before the request resolves', async () => {
    fetchOpportunityPipeline.mockResolvedValue([opportunity()])
    let resolveUpdate
    updateOpportunity.mockReturnValue(
      new Promise((resolve) => {
        resolveUpdate = resolve
      }),
    )
    const user = userEvent.setup()

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    const newColumn = await screen.findByTestId('opportunity-column-new')
    expect(within(newColumn).getByText('New Deal')).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Estágio'), 'contacted')

    const contactedColumn = screen.getByTestId('opportunity-column-contacted')
    await waitFor(() => expect(within(contactedColumn).getByText('New Deal')).toBeInTheDocument())
    expect(within(newColumn).queryByText('New Deal')).not.toBeInTheDocument()

    resolveUpdate(opportunity({ stage: 'contacted' }))
  })

  it('rolls back the card to its original column when the mutation fails', async () => {
    fetchOpportunityPipeline.mockResolvedValue([opportunity()])
    updateOpportunity.mockRejectedValue(new Error('network error'))
    const user = userEvent.setup()

    renderWithProviders(<OpportunitiesPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    await screen.findByTestId('opportunity-column-new')
    await user.selectOptions(screen.getByLabelText('Estágio'), 'contacted')

    await waitFor(() =>
      expect(
        within(screen.getByTestId('opportunity-column-new')).getByText('New Deal'),
      ).toBeInTheDocument(),
    )
    expect(
      within(screen.getByTestId('opportunity-column-contacted')).queryByText('New Deal'),
    ).not.toBeInTheDocument()
  })
})
