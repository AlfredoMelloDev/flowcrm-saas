import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { LostReasonsList } from './LostReasonsList'

describe('LostReasonsList', () => {
  it('shows an empty message when there are no lost reasons', () => {
    render(<LostReasonsList lostReasons={[]} />)

    expect(
      screen.getByText('Nenhuma oportunidade perdida com motivo registrado no período.'),
    ).toBeInTheDocument()
  })

  it('renders each reason with its count', () => {
    render(<LostReasonsList lostReasons={[{ reason: 'price', count: 3 }]} />)

    expect(screen.getByText('price')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
  })
})
