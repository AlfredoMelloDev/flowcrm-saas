import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ClientTable } from './ClientTable'

const baseProps = {
  sort: 'created_at',
  order: 'desc',
  onSortChange: vi.fn(),
  onEdit: vi.fn(),
  onDelete: vi.fn(),
  canDelete: true,
  hasFilters: false,
}

describe('ClientTable', () => {
  it('shows a loading spinner', () => {
    render(<ClientTable {...baseProps} clients={[]} isLoading isError={false} />)

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('shows an error state with a retry action', () => {
    const onRetry = vi.fn()
    render(
      <ClientTable {...baseProps} clients={[]} isLoading={false} isError onRetry={onRetry} />,
    )

    expect(screen.getByText(/não foi possível carregar/i)).toBeInTheDocument()
  })

  it('shows an empty state when there are no clients', () => {
    render(<ClientTable {...baseProps} clients={[]} isLoading={false} isError={false} />)

    expect(screen.getByText('Nenhum cliente ainda')).toBeInTheDocument()
  })

  it('renders client rows and hides delete when canDelete is false', () => {
    const clients = [
      {
        id: 'c1',
        name: 'Jane Client',
        email: 'jane@acme.test',
        phone: null,
        document: '12345678900',
        type: 'individual',
        status: 'active',
        assigned_to: null,
        created_at: '2026-01-01T00:00:00Z',
      },
    ]

    render(
      <ClientTable {...baseProps} clients={clients} isLoading={false} isError={false} canDelete={false} />,
    )

    expect(screen.getAllByText('Jane Client').length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument()
  })
})
