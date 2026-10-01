import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { DailyTrendChart } from './DailyTrendChart'

describe('DailyTrendChart', () => {
  it('shows an empty message when there is no data', () => {
    render(<DailyTrendChart title="Leads" series={[{ label: 'Criados', color: '#000', data: [] }]} />)

    expect(screen.getByText('Sem dados no período selecionado.')).toBeInTheDocument()
  })

  it('renders the title, legend and date range labels', () => {
    render(
      <DailyTrendChart
        title="Leads criados × convertidos"
        series={[
          { label: 'Criados', color: '#4f46e5', data: [{ date: '2026-09-01', count: 2 }, { date: '2026-09-02', count: 1 }] },
          { label: 'Convertidos', color: '#16a34a', data: [{ date: '2026-09-01', count: 1 }, { date: '2026-09-02', count: 0 }] },
        ]}
      />,
    )

    expect(screen.getByText('Leads criados × convertidos')).toBeInTheDocument()
    expect(screen.getByText('Criados')).toBeInTheDocument()
    expect(screen.getByText('Convertidos')).toBeInTheDocument()
    expect(screen.getByRole('img')).toBeInTheDocument()
  })
})
