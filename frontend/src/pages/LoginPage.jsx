import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useLogin } from '../hooks/useLogin'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { getErrorMessage, getFieldError } from '../utils/apiErrors'

export function LoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const login = useLogin()

  function handleSubmit(event) {
    event.preventDefault()
    login.mutate({ email, password })
  }

  return (
    <div>
      <h1 className="mb-1 text-xl font-semibold text-text">Entrar</h1>
      <p className="mb-6 text-sm text-muted">Acesse sua conta do FlowCRM.</p>

      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          id="email"
          label="E-mail"
          type="email"
          autoComplete="username"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          error={getFieldError(login.error, 'email')}
          required
        />
        <Input
          id="password"
          label="Senha"
          type="password"
          autoComplete="current-password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={getFieldError(login.error, 'password')}
          required
        />

        {login.isError && !getFieldError(login.error, 'email') && (
          <p className="text-sm text-danger">{getErrorMessage(login.error)}</p>
        )}

        <Button type="submit" disabled={login.isPending} className="w-full">
          {login.isPending ? 'Entrando…' : 'Entrar'}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-muted">
        Ainda não tem uma empresa cadastrada?{' '}
        <Link to="/register" className="font-medium text-primary hover:underline">
          Criar conta
        </Link>
      </p>
    </div>
  )
}
