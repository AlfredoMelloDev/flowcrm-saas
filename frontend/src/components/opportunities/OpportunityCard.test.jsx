import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { OpportunityCard } from './OpportunityCard'

vi.mock('../../api/opportunities')
import { updateOpportunity } from '../../api/opportunities'

const baseOpportunity = {
  id: 'o1',
  title: 'Big Deal',
  stage: 'new',
  value: '1000.00',
  expected_close_date: null,
  lost_reason: null,
  notes: null,
  client: { id: 'c1', name: 'Acme Co' },
  assigned_to: { id: 'u1', name: 'Bob' },
}

describe('OpportunityCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows title, client, value and responsável, and hides previsão when absent', () => {
    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={vi.fn()} onDelete={vi.fn()} canDelete />,
    )

    expect(screen.getByText('Big Deal')).toBeInTheDocument()
    expect(screen.getByText('Acme Co')).toBeInTheDocument()
    expect(screen.getByText('Bob')).toBeInTheDocument()
    expect(screen.queryByText('Previsão')).not.toBeInTheDocument()
  })

  it('shows previsão de fechamento when present', () => {
    renderWithProviders(
      <OpportunityCard
        opportunity={{ ...baseOpportunity, expected_close_date: '2026-12-01' }}
        onEdit={vi.fn()}
        onDelete={vi.fn()}
        canDelete
      />,
    )

    expect(screen.getByText('Previsão')).toBeInTheDocument()
  })

  it('changes stage directly for a non-lost stage', async () => {
    updateOpportunity.mockResolvedValue({ ...baseOpportunity, stage: 'contacted' })
    const user = userEvent.setup()

    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={vi.fn()} onDelete={vi.fn()} canDelete />,
    )

    await user.selectOptions(screen.getByLabelText('Estágio'), 'contacted')

    await waitFor(() =>
      expect(updateOpportunity).toHaveBeenCalledWith(
        { id: 'o1', payload: { stage: 'contacted' } },
        expect.anything(),
      ),
    )
  })

  it('opens the lost-reason modal instead of firing the mutation when LOST is selected', async () => {
    const user = userEvent.setup()

    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={vi.fn()} onDelete={vi.fn()} canDelete />,
    )

    await user.selectOptions(screen.getByLabelText('Estágio'), 'lost')

    expect(screen.getByText('Motivo da perda')).toBeInTheDocument()
    expect(updateOpportunity).not.toHaveBeenCalled()
  })

  it('only sends lost_reason after the modal is confirmed', async () => {
    updateOpportunity.mockResolvedValue({ ...baseOpportunity, stage: 'lost' })
    const user = userEvent.setup()

    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={vi.fn()} onDelete={vi.fn()} canDelete />,
    )

    await user.selectOptions(screen.getByLabelText('Estágio'), 'lost')
    expect(updateOpportunity).not.toHaveBeenCalled()

    await user.type(screen.getByLabelText('Motivo'), 'Preço muito alto')
    await user.click(screen.getByRole('button', { name: 'Confirmar' }))

    await waitFor(() =>
      expect(updateOpportunity).toHaveBeenCalledWith(
        { id: 'o1', payload: { stage: 'lost', lost_reason: 'Preço muito alto' } },
        expect.anything(),
      ),
    )
  })

  it('discards the pending change when the lost-reason modal is cancelled', async () => {
    const user = userEvent.setup()

    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={vi.fn()} onDelete={vi.fn()} canDelete />,
    )

    await user.selectOptions(screen.getByLabelText('Estágio'), 'lost')
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByText('Motivo da perda')).not.toBeInTheDocument()
    expect(updateOpportunity).not.toHaveBeenCalled()
  })

  it('hides Excluir when canDelete is false', () => {
    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={vi.fn()} onDelete={vi.fn()} canDelete={false} />,
    )

    expect(screen.queryByRole('button', { name: 'Excluir' })).not.toBeInTheDocument()
  })

  it('calls onEdit and onDelete when their buttons are clicked', async () => {
    const onEdit = vi.fn()
    const onDelete = vi.fn()
    const user = userEvent.setup()

    renderWithProviders(
      <OpportunityCard opportunity={baseOpportunity} onEdit={onEdit} onDelete={onDelete} canDelete />,
    )

    await user.click(screen.getByRole('button', { name: 'Editar' }))
    expect(onEdit).toHaveBeenCalledWith(baseOpportunity)

    await user.click(screen.getByRole('button', { name: 'Excluir' }))
    expect(onDelete).toHaveBeenCalledWith(baseOpportunity)
  })
})
