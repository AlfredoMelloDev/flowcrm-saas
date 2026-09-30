import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ClosingSoonList } from './ClosingSoonList'

describe('ClosingSoonList', () => {
  it('shows an empty message when there are no opportunities', () => {
    render(<ClosingSoonList opportunities={[]} />)

    expect(screen.getByText('Nenhuma oportunidade com fechamento próximo.')).toBeInTheDocument()
  })

  it('renders opportunity title, client, value and expected close date', () => {
    render(
      <ClosingSoonList
        opportunities={[
          {
            id: 'o1',
            title: 'Big Deal',
            value: '1500.00',
            expected_close_date: '2026-10-05T00:00:00.000000Z',
            client: { id: 'c1', name: 'Acme Co' },
          },
        ]}
      />,
    )

    expect(screen.getByText('Big Deal')).toBeInTheDocument()
    expect(screen.getByText('Acme Co')).toBeInTheDocument()
    expect(screen.getByText('R$ 1.500,00')).toBeInTheDocument()
  })
})
