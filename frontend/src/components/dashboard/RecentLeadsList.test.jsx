import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { RecentLeadsList } from './RecentLeadsList'

describe('RecentLeadsList', () => {
  it('shows an empty message when there are no leads', () => {
    render(<RecentLeadsList leads={[]} />)

    expect(screen.getByText('Nenhum lead criado ainda.')).toBeInTheDocument()
  })

  it('renders lead name, assignee and status', () => {
    render(
      <RecentLeadsList
        leads={[
          {
            id: 'l1',
            name: 'Jane Prospect',
            status: 'new',
            assigned_to: { id: 'u1', name: 'Bob' },
            created_at: '2026-01-01T00:00:00Z',
          },
        ]}
      />,
    )

    expect(screen.getByText('Jane Prospect')).toBeInTheDocument()
    expect(screen.getByText('Bob')).toBeInTheDocument()
    expect(screen.getByText('Novo')).toBeInTheDocument()
  })
})
