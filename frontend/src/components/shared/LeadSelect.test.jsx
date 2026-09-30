import { screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { LeadSelect } from './LeadSelect'

vi.mock('../../api/leads')
import { fetchLeadOptions } from '../../api/leads'

describe('LeadSelect', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lists the fetched lead options', async () => {
    fetchLeadOptions.mockResolvedValue([
      { id: 'l1', name: 'John Doe' },
      { id: 'l2', name: 'Jane Roe' },
    ])

    renderWithProviders(<LeadSelect id="lead" value="" onChange={vi.fn()} />)

    expect(await screen.findByRole('option', { name: 'John Doe' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Jane Roe' })).toBeInTheDocument()
  })

  it('disables the select while options are loading', () => {
    fetchLeadOptions.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<LeadSelect id="lead" value="" onChange={vi.fn()} />)

    expect(screen.getByLabelText('Lead')).toBeDisabled()
  })

  it('injects the current lead as an option when it is no longer in the list', async () => {
    fetchLeadOptions.mockResolvedValue([{ id: 'l1', name: 'John Doe' }])

    renderWithProviders(
      <LeadSelect id="lead" value="l9" onChange={vi.fn()} currentLead={{ id: 'l9', name: 'Gone Lead' }} />,
    )

    const option = await screen.findByRole('option', { name: /Gone Lead/ })
    expect(option).toBeInTheDocument()
    expect(screen.getByLabelText('Lead')).toHaveValue('l9')
  })

  it('does not duplicate the current lead when it is already in the list', async () => {
    fetchLeadOptions.mockResolvedValue([{ id: 'l1', name: 'John Doe' }])

    renderWithProviders(
      <LeadSelect id="lead" value="l1" onChange={vi.fn()} currentLead={{ id: 'l1', name: 'John Doe' }} />,
    )

    expect(await screen.findAllByRole('option', { name: 'John Doe' })).toHaveLength(1)
  })
})
