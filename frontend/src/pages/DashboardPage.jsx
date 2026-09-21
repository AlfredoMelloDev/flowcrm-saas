import { useAuth } from '../hooks/useAuth'

export function DashboardPage() {
  const { user } = useAuth()

  return (
    <div>
      <h1 className="text-xl font-semibold text-text">Olá, {user?.name}</h1>
      <p className="mt-1 text-sm text-muted">
        Bem-vindo(a) de volta ao {user?.company?.name}.
      </p>

      <div className="mt-6 rounded-xl border border-dashed border-border bg-surface p-8 text-center">
        <p className="text-sm text-muted">
          Métricas e indicadores do painel ainda não estão disponíveis. Esta
          área será conectada a um endpoint de dashboard dedicado em uma
          próxima fase.
        </p>
      </div>
    </div>
  )
}
