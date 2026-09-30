import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { LeadTable } from './LeadTable'

const baseProps = {
  sort: 'created_at',
  order: 'desc',
  onSortChange: vi.fn(),
  onEdit: vi.fn(),
  onDelete: vi.fn(),
  canDelete: true,
  hasFilters: false,
}

describe('LeadTable', () => {
  it('shows a loading spinner', () => {
    render(<LeadTable {...baseProps} leads={[]} isLoading isError={false} />)

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action', () => {
    const onRetry = vi.fn()
    render(
      <LeadTable {...baseProps} leads={[]} isLoading={false} isError onRetry={onRetry} />,
    )

    expect(screen.getByText(/não foi possível carregar/i)).toBeInTheDocument()
  })

  it('shows an empty state when there are no leads', () => {
    render(<LeadTable {...baseProps} leads={[]} isLoading={false} isError={false} />)

    expect(screen.getByText('Nenhum lead ainda')).toBeInTheDocument()
  })

  it('renders lead rows and hides delete when canDelete is false', () => {
    const leads = [
      {
        id: 'l1',
        name: 'Jane Prospect',
        email: 'jane@acme.test',
        phone: null,
        status: 'new',
        source: 'website',
        estimated_value: '1000.00',
        assigned_to: null,
        created_at: '2026-01-01T00:00:00Z',
      },
    ]

    render(<LeadTable {...baseProps} leads={leads} isLoading={false} isError={false} canDelete={false} />)

    expect(screen.getAllByText('Jane Prospect').length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument()
  })

  it('shows Converter when canConvert allows it, and calls onConvert', async () => {
    const leads = [
      {
        id: 'l1',
        name: 'Jane Prospect',
        email: null,
        phone: null,
        status: 'new',
        source: null,
        estimated_value: null,
        assigned_to: null,
        created_at: '2026-01-01T00:00:00Z',
      },
    ]
    const onConvert = vi.fn()

    render(
      <LeadTable
        {...baseProps}
        leads={leads}
        isLoading={false}
        isError={false}
        onConvert={onConvert}
        canConvert={() => true}
      />,
    )

    const buttons = screen.getAllByRole('button', { name: 'Converter' })
    buttons[0].click()
    expect(onConvert).toHaveBeenCalledWith(leads[0])
  })

  it('hides Converter when canConvert returns false for that lead', () => {
    const leads = [
      {
        id: 'l1',
        name: 'Jane Prospect',
        email: null,
        phone: null,
        status: 'converted',
        source: null,
        estimated_value: null,
        assigned_to: null,
        created_at: '2026-01-01T00:00:00Z',
      },
    ]

    render(
      <LeadTable
        {...baseProps}
        leads={leads}
        isLoading={false}
        isError={false}
        onConvert={vi.fn()}
        canConvert={(lead) => lead.status !== 'converted'}
      />,
    )

    expect(screen.queryByRole('button', { name: 'Converter' })).not.toBeInTheDocument()
  })
})
