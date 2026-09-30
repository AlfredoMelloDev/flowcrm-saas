import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { PipelineByStageSummary } from './PipelineByStageSummary'

const stages = [
  { stage: 'new', count: 2, value: '100.00' },
  { stage: 'contacted', count: 1, value: '50.00' },
  { stage: 'proposal', count: 0, value: '0.00' },
  { stage: 'negotiation', count: 0, value: '0.00' },
  { stage: 'won', count: 3, value: '300.00' },
  { stage: 'lost', count: 1, value: '20.00' },
]

describe('PipelineByStageSummary', () => {
  it('renders all six stages with their count and value', () => {
    render(<PipelineByStageSummary stages={stages} />)

    expect(screen.getByText('Novo')).toBeInTheDocument()
    expect(screen.getByText('Contatado')).toBeInTheDocument()
    expect(screen.getByText('Proposta')).toBeInTheDocument()
    expect(screen.getByText('Negociação')).toBeInTheDocument()
    expect(screen.getByText('Ganho')).toBeInTheDocument()
    expect(screen.getByText('Perdido')).toBeInTheDocument()
    expect(screen.getByText(/2 ·/)).toBeInTheDocument()
  })
})
