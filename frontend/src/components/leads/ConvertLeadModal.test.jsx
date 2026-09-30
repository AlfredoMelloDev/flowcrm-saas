import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../../test/utils'
import { ConvertLeadModal } from './ConvertLeadModal'

vi.mock('../../api/leads')
import { convertLead } from '../../api/leads'

const lead = {
  id: 'l1',
  name: 'Jane Prospect',
  email: 'jane@prospect.test',
  phone: '11999999999',
  status: 'qualified',
  estimated_value: '2500.00',
}

function renderModal(onClose = vi.fn()) {
  return renderWithProviders(
    <Routes>
      <Route path="/leads" element={<ConvertLeadModal lead={lead} onClose={onClose} />} />
      <Route path="/opportunities" element={<div>Opportunities board</div>} />
    </Routes>,
    { route: '/leads', authUser: { id: '1', name: 'Alice', role: 'admin' } },
  )
}

describe('ConvertLeadModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('pre-fills the opportunity title and value from the lead', () => {
    renderModal()

    expect(screen.getByLabelText('Título da oportunidade')).toHaveValue('Jane Prospect')
    expect(screen.getByLabelText('Valor da oportunidade')).toHaveValue('2500.00')
    expect(screen.getByLabelText('Tipo de cliente')).toHaveValue('individual')
  })

  it('shows the lead\'s read-only contact info', () => {
    renderModal()

    expect(screen.getByText('Jane Prospect')).toBeInTheDocument()
    expect(screen.getByText('jane@prospect.test')).toBeInTheDocument()
    expect(screen.getByText('11999999999')).toBeInTheDocument()
  })

  it('submits the default payload without parseFloat, preserving value as a string', async () => {
    convertLead.mockResolvedValue({ lead: {}, client: {}, opportunity: {} })
    const user = userEvent.setup()

    renderModal()

    await user.click(screen.getByRole('button', { name: 'Confirmar conversão' }))

    await waitFor(() =>
      expect(convertLead).toHaveBeenCalledWith(
        {
          id: 'l1',
          payload: {
            client_document: null,
            client_type: 'individual',
            opportunity_title: 'Jane Prospect',
            opportunity_value: '2500.00',
            expected_close_date: null,
            notes: null,
          },
        },
        expect.anything(),
      ),
    )

    const [[{ payload }]] = convertLead.mock.calls
    expect(typeof payload.opportunity_value).toBe('string')
  })

  it('submits a custom opportunity_value when the user changes it', async () => {
    convertLead.mockResolvedValue({ lead: {}, client: {}, opportunity: {} })
    const user = userEvent.setup()

    renderModal()

    const valueInput = screen.getByLabelText('Valor da oportunidade')
    await user.clear(valueInput)
    await user.type(valueInput, '9999.99')
    await user.click(screen.getByRole('button', { name: 'Confirmar conversão' }))

    await waitFor(() =>
      expect(convertLead).toHaveBeenCalledWith(
        expect.objectContaining({
          payload: expect.objectContaining({ opportunity_value: '9999.99' }),
        }),
        expect.anything(),
      ),
    )
  })

  it('submits optional client_document and client_type when provided', async () => {
    convertLead.mockResolvedValue({ lead: {}, client: {}, opportunity: {} })
    const user = userEvent.setup()

    renderModal()

    await user.type(screen.getByLabelText('Documento do cliente'), '12345678900')
    await user.selectOptions(screen.getByLabelText('Tipo de cliente'), 'company')
    await user.click(screen.getByRole('button', { name: 'Confirmar conversão' }))

    await waitFor(() =>
      expect(convertLead).toHaveBeenCalledWith(
        expect.objectContaining({
          payload: expect.objectContaining({
            client_document: '12345678900',
            client_type: 'company',
          }),
        }),
        expect.anything(),
      ),
    )
  })

  it('disables the confirm button while the mutation is pending, preventing double submit', async () => {
    convertLead.mockReturnValue(new Promise(() => {}))
    const user = userEvent.setup()

    renderModal()

    const button = screen.getByRole('button', { name: 'Confirmar conversão' })
    await user.click(button)

    expect(screen.getByRole('button', { name: 'Convertendo…' })).toBeDisabled()
    expect(convertLead).toHaveBeenCalledTimes(1)
  })

  it('shows a friendly message on a 409 (already converted)', async () => {
    convertLead.mockRejectedValue({ response: { status: 409 } })
    const user = userEvent.setup()

    renderModal()

    await user.click(screen.getByRole('button', { name: 'Confirmar conversão' }))

    expect(await screen.findByText('Este lead já foi convertido.')).toBeInTheDocument()
  })

  it('shows a 422 field error next to the offending field', async () => {
    convertLead.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { client_document: ['The client document has already been taken.'] } },
      },
    })
    const user = userEvent.setup()

    renderModal()

    await user.click(screen.getByRole('button', { name: 'Confirmar conversão' }))

    expect(
      await screen.findByText('The client document has already been taken.'),
    ).toBeInTheDocument()
  })

  it('closes the modal and navigates to /opportunities on success', async () => {
    convertLead.mockResolvedValue({ lead: {}, client: {}, opportunity: {} })
    const onClose = vi.fn()
    const user = userEvent.setup()

    renderModal(onClose)

    await user.click(screen.getByRole('button', { name: 'Confirmar conversão' }))

    expect(await screen.findByText('Opportunities board')).toBeInTheDocument()
    expect(onClose).toHaveBeenCalled()
  })
})
