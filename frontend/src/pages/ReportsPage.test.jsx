import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { ReportsPage } from './ReportsPage'

vi.mock('../api/reports')
vi.mock('../api/users')
vi.mock('../api/auth')
import { fetchReports } from '../api/reports'
import { fetchAssignableUsers } from '../api/users'

const summary = {
  period: { date_from: '2026-09-01', date_to: '2026-09-30', timezone: 'UTC' },
  leads_created_total: 10,
  leads_converted_total: 4,
  lead_conversion_rate: 40,
  avg_conversion_time_hours: 24.5,
  opportunities_created_total: 8,
  opportunities_won_total: 3,
  opportunities_lost_total: 1,
  value_won: '3000.00',
  value_lost: '500.00',
  avg_closing_time_hours: 72,
  lost_reasons: [{ reason: 'price', count: 1 }],
  activities_created_total: 20,
  activities_completed_total: 15,
  activities_completed_by_type: [{ type: 'call', count: 10 }, { type: 'email', count: 5 }],
  daily_series: {
    leads_created: [{ date: '2026-09-29', count: 2 }, { date: '2026-09-30', count: 1 }],
    leads_converted: [{ date: '2026-09-29', count: 1 }, { date: '2026-09-30', count: 0 }],
    opportunities_won: [{ date: '2026-09-29', count: 1 }, { date: '2026-09-30', count: 0 }],
    opportunities_lost: [{ date: '2026-09-29', count: 0 }, { date: '2026-09-30', count: 1 }],
    activities_completed: [{ date: '2026-09-29', count: 5 }, { date: '2026-09-30', count: 3 }],
  },
  performance_by_user: [
    { user_id: 'u1', name: 'Bob', leads_converted: 2, opportunities_won: 1, value_won: '1000.00', activities_completed: 4 },
  ],
  pipeline_snapshot: {
    by_stage: [
      { stage: 'new', count: 1, value: '100.00' },
      { stage: 'contacted', count: 0, value: '0.00' },
      { stage: 'proposal', count: 0, value: '0.00' },
      { stage: 'negotiation', count: 0, value: '0.00' },
      { stage: 'won', count: 3, value: '3000.00' },
      { stage: 'lost', count: 1, value: '500.00' },
    ],
    open_value: '100.00',
  },
}

describe('ReportsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchAssignableUsers.mockResolvedValue([{ id: 'u1', name: 'Bob', email: 'bob@acme.test' }])
  })

  it('shows a loading spinner while the report is loading', () => {
    fetchReports.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<ReportsPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action', async () => {
    fetchReports.mockRejectedValue(new Error('network error'))

    renderWithProviders(<ReportsPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(await screen.findByText(/não foi possível carregar/i)).toBeInTheDocument()
  })

  it('renders metrics, charts and tables once loaded', async () => {
    fetchReports.mockResolvedValue(summary)

    renderWithProviders(<ReportsPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    expect(await screen.findByText('Leads criados')).toBeInTheDocument()
    expect(screen.getAllByText('10').length).toBeGreaterThan(0)
    expect(screen.getByText('40%')).toBeInTheDocument()
    expect(screen.getByText('R$ 3.000,00')).toBeInTheDocument()
    expect(screen.getByText('Leads criados × convertidos')).toBeInTheDocument()
    expect(screen.getByText('Oportunidades ganhas × perdidas')).toBeInTheDocument()
    expect(screen.getByText('Atividades concluídas por tipo')).toBeInTheDocument()
    expect(screen.getByText('Motivos de perda')).toBeInTheDocument()
    expect(screen.getByText('price')).toBeInTheDocument()
    expect(screen.getByText('Desempenho por responsável')).toBeInTheDocument()
    expect(screen.getAllByText('Bob').length).toBeGreaterThan(0)
  })

  it('shows the "Responsável" filter for admin and re-fetches with the chosen user', async () => {
    fetchReports.mockResolvedValue(summary)
    const user = userEvent.setup()

    renderWithProviders(<ReportsPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    const filter = await screen.findByLabelText('Responsável')
    await screen.findByRole('option', { name: 'Bob' })
    await user.selectOptions(filter, 'u1')

    await waitFor(() => expect(fetchReports).toHaveBeenCalledWith(expect.objectContaining({ user_id: 'u1' })))
  })

  it('hides the "Responsável" filter for seller and never fetches assignable users', async () => {
    fetchReports.mockResolvedValue(summary)

    renderWithProviders(<ReportsPage />, { authUser: { id: '2', name: 'Sam', role: 'seller' } })
    await screen.findByText('Leads criados')

    expect(screen.queryByLabelText('Responsável')).not.toBeInTheDocument()
    expect(fetchAssignableUsers).not.toHaveBeenCalled()
  })

  it('never touches localStorage or sessionStorage', async () => {
    fetchReports.mockResolvedValue(summary)
    const setLocal = vi.spyOn(Storage.prototype, 'setItem')

    renderWithProviders(<ReportsPage />, { authUser: { id: '1', name: 'Alice', role: 'admin' } })

    await waitFor(() => expect(screen.getByText('Leads criados')).toBeInTheDocument())

    expect(setLocal).not.toHaveBeenCalled()
    setLocal.mockRestore()
  })
})
