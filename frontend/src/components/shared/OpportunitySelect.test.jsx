import { screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { OpportunitySelect } from './OpportunitySelect'

vi.mock('../../api/opportunities')
import { fetchOpportunityOptions } from '../../api/opportunities'

describe('OpportunitySelect', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lists the fetched opportunity options', async () => {
    fetchOpportunityOptions.mockResolvedValue([
      { id: 'o1', title: 'Big Deal' },
      { id: 'o2', title: 'Small Deal' },
    ])

    renderWithProviders(<OpportunitySelect id="opportunity" value="" onChange={vi.fn()} />)

    expect(await screen.findByRole('option', { name: 'Big Deal' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Small Deal' })).toBeInTheDocument()
  })

  it('disables the select while options are loading', () => {
    fetchOpportunityOptions.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<OpportunitySelect id="opportunity" value="" onChange={vi.fn()} />)

    expect(screen.getByLabelText('Oportunidade')).toBeDisabled()
  })

  it('injects the current opportunity as an option when it is no longer in the list', async () => {
    fetchOpportunityOptions.mockResolvedValue([{ id: 'o1', title: 'Big Deal' }])

    renderWithProviders(
      <OpportunitySelect
        id="opportunity"
        value="o9"
        onChange={vi.fn()}
        currentOpportunity={{ id: 'o9', title: 'Gone Deal' }}
      />,
    )

    const option = await screen.findByRole('option', { name: /Gone Deal/ })
    expect(option).toBeInTheDocument()
    expect(screen.getByLabelText('Oportunidade')).toHaveValue('o9')
  })

  it('does not duplicate the current opportunity when it is already in the list', async () => {
    fetchOpportunityOptions.mockResolvedValue([{ id: 'o1', title: 'Big Deal' }])

    renderWithProviders(
      <OpportunitySelect
        id="opportunity"
        value="o1"
        onChange={vi.fn()}
        currentOpportunity={{ id: 'o1', title: 'Big Deal' }}
      />,
    )

    expect(await screen.findAllByRole('option', { name: 'Big Deal' })).toHaveLength(1)
  })
})
