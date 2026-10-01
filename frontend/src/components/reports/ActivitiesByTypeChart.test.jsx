import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ActivitiesByTypeChart } from './ActivitiesByTypeChart'

describe('ActivitiesByTypeChart', () => {
  it('shows an empty message when there are no completed activities', () => {
    render(<ActivitiesByTypeChart activitiesByType={[]} />)

    expect(screen.getByText('Nenhuma atividade concluída no período.')).toBeInTheDocument()
  })

  it('renders each type with its label and count', () => {
    render(<ActivitiesByTypeChart activitiesByType={[{ type: 'call', count: 10 }, { type: 'email', count: 5 }]} />)

    expect(screen.getByText('Ligação')).toBeInTheDocument()
    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getByText('E-mail')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
  })
})
