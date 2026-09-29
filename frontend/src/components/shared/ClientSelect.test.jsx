import { screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { ClientSelect } from './ClientSelect'

vi.mock('../../api/clients')
import { fetchClientOptions } from '../../api/clients'

describe('ClientSelect', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lists the fetched client options', async () => {
    fetchClientOptions.mockResolvedValue([
      { id: 'c1', name: 'Acme Co', document: '123' },
      { id: 'c2', name: 'Beta Ltda', document: '456' },
    ])

    renderWithProviders(<ClientSelect id="client" value="" onChange={vi.fn()} />)

    expect(await screen.findByRole('option', { name: 'Acme Co' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Beta Ltda' })).toBeInTheDocument()
  })

  it('disables the select while options are loading', () => {
    fetchClientOptions.mockReturnValue(new Promise(() => {}))

    renderWithProviders(<ClientSelect id="client" value="" onChange={vi.fn()} />)

    expect(screen.getByLabelText('Cliente')).toBeDisabled()
  })

  it('injects the current client as an option when it is no longer in the active list', async () => {
    fetchClientOptions.mockResolvedValue([{ id: 'c1', name: 'Acme Co', document: '123' }])

    renderWithProviders(
      <ClientSelect
        id="client"
        value="c9"
        onChange={vi.fn()}
        currentClient={{ id: 'c9', name: 'Inactive Co' }}
      />,
    )

    const option = await screen.findByRole('option', { name: /Inactive Co/ })
    expect(option).toBeInTheDocument()
    expect(screen.getByLabelText('Cliente')).toHaveValue('c9')
  })

  it('does not duplicate the current client when it is already in the active list', async () => {
    fetchClientOptions.mockResolvedValue([{ id: 'c1', name: 'Acme Co', document: '123' }])

    renderWithProviders(
      <ClientSelect
        id="client"
        value="c1"
        onChange={vi.fn()}
        currentClient={{ id: 'c1', name: 'Acme Co' }}
      />,
    )

    expect(await screen.findAllByRole('option', { name: 'Acme Co' })).toHaveLength(1)
  })
})
