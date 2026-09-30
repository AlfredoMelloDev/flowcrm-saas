import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { DashboardPage } from './DashboardPage'

vi.mock('../api/dashboard')
vi.mock('../api/auth')
import { fetchDashboard } from '../api/dashboard'

const summary = {
  leads_active: 3,
  clients_active: 2,
  opportunities_open: 4,
  pipeline_value: '1234.50',
  opportunities_won: 5,
  opportunities_lost: 1,
  lead_conversion_rate: 33.3,
  pipeline_by_stage: [
    { stage: 'new', count: 1, value: '100.00' },
    { stage: 'contacted', count: 1, value: '100.00' },
    { stage: 'proposal', count: 1, value: '100.00' },
    { stage: 'negotiation', count: 1, value: '934.50' },
    { stage: 'won', count: 5, value: '5000.00' },
    { stage: 'lost', count: 1, value: '200.00' },
  ],
  closing_soon: [
    {
      id: 'o1',
      title: 'Renewal Deal',
      value: '500.00',
      expected_close_date: '2026-10-05T00:00:00.000000Z',
      client: { id: 'c1', name: 'Acme Co' },
    },
  ],
  recent_leads: [
    {
      id: 'l1',
      name: 'Jane Prospect',
      status: 'new',
      assigned_to: { id: 'u1', name: 'Bob' },
      created_at: '2026-01-01T00:00:00Z',
    },
  ],
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows a loading spinner while the dashboard is loading', () => {
    fetchDashboard.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<DashboardPage />, {
      authUser: { id: '1', name: 'Alice', role: 'admin', company: { name: 'Acme' } },
    })

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action', async () => {
    fetchDashboard.mockRejectedValue(new Error('network error'))
    const user = userEvent.setup()

    renderWithProviders(<DashboardPage />, {
      authUser: { id: '1', name: 'Alice', role: 'admin', company: { name: 'Acme' } },
    })

    expect(await screen.findByText(/não foi possível carregar/i)).toBeInTheDocument()

    fetchDashboard.mockResolvedValue(summary)
    await user.click(screen.getByRole('button', { name: /tentar novamente/i }))

    expect(await screen.findByText('Leads ativos')).toBeInTheDocument()
  })

  it('renders every metric and section once loaded', async () => {
    fetchDashboard.mockResolvedValue(summary)

    renderWithProviders(<DashboardPage />, {
      authUser: { id: '1', name: 'Alice', role: 'admin', company: { name: 'Acme' } },
    })

    expect(await screen.findByText('Leads ativos')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText('R$ 1.234,50')).toBeInTheDocument()
    expect(screen.getByText('33.3%')).toBeInTheDocument()
    expect(screen.getByText('Pipeline por estágio')).toBeInTheDocument()
    expect(screen.getByText('Renewal Deal')).toBeInTheDocument()
    expect(screen.getByText('Jane Prospect')).toBeInTheDocument()
  })

  it('never touches localStorage or sessionStorage', async () => {
    fetchDashboard.mockResolvedValue(summary)
    const setLocal = vi.spyOn(Storage.prototype, 'setItem')

    renderWithProviders(<DashboardPage />, {
      authUser: { id: '1', name: 'Alice', role: 'admin', company: { name: 'Acme' } },
    })

    await waitFor(() => expect(screen.getByText('Leads ativos')).toBeInTheDocument())

    expect(setLocal).not.toHaveBeenCalled()
    setLocal.mockRestore()
  })
})
