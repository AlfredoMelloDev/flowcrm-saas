import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { PerformanceByUserTable } from './PerformanceByUserTable'

describe('PerformanceByUserTable', () => {
  it('shows an empty message when there is no performance data', () => {
    render(<PerformanceByUserTable performanceByUser={[]} />)

    expect(screen.getByText('Nenhum dado de desempenho no período.')).toBeInTheDocument()
  })

  it('renders a row per user with formatted currency', () => {
    render(
      <PerformanceByUserTable
        performanceByUser={[
          { user_id: 'u1', name: 'Bob', leads_converted: 2, opportunities_won: 1, value_won: '1000.00', activities_completed: 4 },
        ]}
      />,
    )

    expect(screen.getByText('Bob')).toBeInTheDocument()
    expect(screen.getByText('R$ 1.000,00')).toBeInTheDocument()
  })
})
