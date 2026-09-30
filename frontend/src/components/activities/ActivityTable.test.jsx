import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ActivityTable } from './ActivityTable'

const baseProps = {
  sort: 'scheduled_at',
  order: 'asc',
  onSortChange: vi.fn(),
  onEdit: vi.fn(),
  onDelete: vi.fn(),
  onComplete: vi.fn(),
  onReopen: vi.fn(),
  canDelete: true,
  hasFilters: false,
}

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

describe('ActivityTable', () => {
  it('shows a loading spinner', () => {
    render(<ActivityTable {...baseProps} activities={[]} isLoading isError={false} />)

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action', () => {
    const onRetry = vi.fn()
    render(<ActivityTable {...baseProps} activities={[]} isLoading={false} isError onRetry={onRetry} />)

    expect(screen.getByText(/não foi possível carregar/i)).toBeInTheDocument()
  })

  it('shows an empty state when there are no activities', () => {
    render(<ActivityTable {...baseProps} activities={[]} isLoading={false} isError={false} />)

    expect(screen.getByText('Nenhuma atividade ainda')).toBeInTheDocument()
  })

  it('renders the related Lead/Client/Opportunity label for each relation kind', () => {
    const activities = [
      activity({ id: 'a1', lead: { id: 'l1', name: 'John Doe' } }),
      activity({ id: 'a2', client: { id: 'c1', name: 'Acme Co' } }),
      activity({ id: 'a3', opportunity: { id: 'o1', title: 'Big Deal' } }),
      activity({ id: 'a4' }),
    ]

    render(<ActivityTable {...baseProps} activities={activities} isLoading={false} isError={false} />)

    expect(screen.getAllByText('Lead: John Doe').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Cliente: Acme Co').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Oportunidade: Big Deal').length).toBeGreaterThan(0)
    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })

  it('shows "Concluir" for a pending activity and calls onComplete', async () => {
    const onComplete = vi.fn()
    const user = userEvent.setup()

    render(
      <ActivityTable
        {...baseProps}
        onComplete={onComplete}
        activities={[activity({ status: 'pending' })]}
        isLoading={false}
        isError={false}
      />,
    )

    await user.click(screen.getAllByRole('button', { name: 'Concluir' })[0])
    expect(onComplete).toHaveBeenCalledWith(expect.objectContaining({ id: 'a1' }))
  })

  it('shows "Reabrir" for a completed activity and calls onReopen', async () => {
    const onReopen = vi.fn()
    const user = userEvent.setup()

    render(
      <ActivityTable
        {...baseProps}
        onReopen={onReopen}
        activities={[activity({ status: 'completed' })]}
        isLoading={false}
        isError={false}
      />,
    )

    await user.click(screen.getAllByRole('button', { name: 'Reabrir' })[0])
    expect(onReopen).toHaveBeenCalledWith(expect.objectContaining({ id: 'a1' }))
  })

  it('hides Excluir when canDelete is false', () => {
    render(
      <ActivityTable
        {...baseProps}
        canDelete={false}
        activities={[activity()]}
        isLoading={false}
        isError={false}
      />,
    )

    expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument()
  })
})
