import { useAuth } from '../hooks/useAuth'
import { useDashboard } from '../hooks/useDashboard'
import { Spinner } from '../components/ui/Spinner'
import { ErrorState } from '../components/ui/ErrorState'
import { MetricCard } from '../components/dashboard/MetricCard'
import { PipelineByStageSummary } from '../components/dashboard/PipelineByStageSummary'
import { ClosingSoonList } from '../components/dashboard/ClosingSoonList'
import { RecentLeadsList } from '../components/dashboard/RecentLeadsList'
import { formatCurrency } from '../utils/formatters'

export function DashboardPage() {
  const { user } = useAuth()
  const { data, isLoading, isError, refetch } = useDashboard()

  return (
    <div>
      <h1 className="text-xl font-semibold text-text">Olá, {user?.name}</h1>
      <p className="mt-1 text-sm text-muted">
        Bem-vindo(a) de volta ao {user?.company?.name}.
      </p>

      {isLoading && (
        <div className="mt-6 flex justify-center py-16">
          <Spinner />
        </div>
      )}

      {!isLoading && isError && (
        <div className="mt-6">
          <ErrorState onRetry={refetch} />
        </div>
      )}

      {!isLoading && !isError && data && (
        <div className="mt-6 flex flex-col gap-6">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label="Leads ativos" value={data.leads_active} />
            <MetricCard label="Clientes ativos" value={data.clients_active} />
            <MetricCard label="Oportunidades abertas" value={data.opportunities_open} />
            <MetricCard label="Valor do pipeline" value={formatCurrency(data.pipeline_value)} />
            <MetricCard label="Oportunidades ganhas" value={data.opportunities_won} />
            <MetricCard label="Oportunidades perdidas" value={data.opportunities_lost} />
            <MetricCard label="Taxa de conversão de leads" value={`${data.lead_conversion_rate}%`} />
          </div>

          <PipelineByStageSummary stages={data.pipeline_by_stage} />

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ClosingSoonList opportunities={data.closing_soon} />
            <RecentLeadsList leads={data.recent_leads} />
          </div>
        </div>
      )}
    </div>
  )
}
