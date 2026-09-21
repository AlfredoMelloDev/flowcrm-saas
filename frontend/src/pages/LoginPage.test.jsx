import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test/utils'
import { LoginPage } from './LoginPage'

vi.mock('../api/auth')
import { login } from '../api/auth'

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('submits the typed credentials', async () => {
    login.mockResolvedValue({ id: '1', name: 'Alice', role: 'admin' })
    const user = userEvent.setup()

    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('E-mail'), 'alice@acme.test')
    await user.type(screen.getByLabelText('Senha'), 'password123')
    await user.click(screen.getByRole('button', { name: /entrar/i }))

    await waitFor(() =>
      expect(login).toHaveBeenCalledWith(
        { email: 'alice@acme.test', password: 'password123' },
        expect.anything(),
      ),
    )
  })

  it('maps a 422 validation error to the email field', async () => {
    login.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { email: ['These credentials do not match our records.'] } },
      },
    })
    const user = userEvent.setup()

    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('E-mail'), 'alice@acme.test')
    await user.type(screen.getByLabelText('Senha'), 'wrong-password')
    await user.click(screen.getByRole('button', { name: /entrar/i }))

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
  })
})
