import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useRegister } from '../hooks/useRegister'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { getErrorMessage, getFieldError } from '../utils/apiErrors'

export function RegisterPage() {
  const [companyName, setCompanyName] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const register = useRegister()

  function handleSubmit(event) {
    event.preventDefault()
    register.mutate({
      company: { name: companyName },
      user: {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      },
    })
  }

  return (
    <div>
      <h1 className="mb-1 text-xl font-semibold text-text">Criar sua empresa</h1>
      <p className="mb-6 text-sm text-muted">
        Este cadastro cria uma nova empresa no FlowCRM e a sua conta como
        administrador dela. Para adicionar outros vendedores depois, use a
        gestão de equipe dentro do sistema.
      </p>

      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          id="companyName"
          label="Nome da empresa"
          value={companyName}
          onChange={(event) => setCompanyName(event.target.value)}
          error={getFieldError(register.error, 'company.name')}
          required
        />
        <Input
          id="name"
          label="Seu nome"
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={getFieldError(register.error, 'user.name')}
          required
        />
        <Input
          id="email"
          label="Seu e-mail"
          type="email"
          autoComplete="username"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          error={getFieldError(register.error, 'user.email')}
          required
        />
        <Input
          id="password"
          label="Senha"
          type="password"
          autoComplete="new-password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={getFieldError(register.error, 'user.password')}
          required
        />
        <Input
          id="passwordConfirmation"
          label="Confirmar senha"
          type="password"
          autoComplete="new-password"
          value={passwordConfirmation}
          onChange={(event) => setPasswordConfirmation(event.target.value)}
          required
        />

        {register.isError && !getFieldError(register.error, 'user.email') && (
          <p className="text-sm text-danger">{getErrorMessage(register.error)}</p>
        )}

        <Button type="submit" disabled={register.isPending} className="w-full">
          {register.isPending ? 'Criando…' : 'Criar empresa e conta de administrador'}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-muted">
        Já tem uma conta?{' '}
        <Link to="/login" className="font-medium text-primary hover:underline">
          Entrar
        </Link>
      </p>
    </div>
  )
}
