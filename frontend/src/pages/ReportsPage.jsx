import { useSearchParams } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useReports } from '../hooks/useReports'
import { Spinner } from '../components/ui/Spinner'
import { ErrorState } from '../components/ui/ErrorState'
import { MetricCard } from '../components/dashboard/MetricCard'
import { ReportsFilters } from '../components/reports/ReportsFilters'
import { DailyTrendChart } from '../components/reports/DailyTrendChart'
import { ActivitiesByTypeChart } from '../components/reports/ActivitiesByTypeChart'
import { LostReasonsList } from '../components/reports/LostReasonsList'
import { PerformanceByUserTable } from '../components/reports/PerformanceByUserTable'
import { PipelineByStageSummary } from '../components/dashboard/PipelineByStageSummary'
import { formatCurrency, formatDateOnly } from '../utils/formatters'

export function ReportsPage() {
  const { role } = useAuth()
  const canFilterByUser = role === 'admin' || role === 'manager'

  const [searchParams, setSearchParams] = useSearchParams()
  const dateFrom = searchParams.get('date_from') ?? ''
  const dateTo = searchParams.get('date_to') ?? ''
  const userId = searchParams.get('user_id') ?? ''

  function updateParams(next) {
    const params = new URLSearchParams(searchParams)
    for (const [key, value] of Object.entries(next)) {
      if (value) {
        params.set(key, value)
      } else {
        params.delete(key)
      }
    }
    setSearchParams(params)
  }

  const { data, isLoading, isError, refetch } = useReports({
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    user_id: userId || undefined,
  })

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-xl font-semibold text-text">Relatórios</h1>
        {data && (
          <p className="mt-1 text-sm text-muted">
            Período: {formatDateOnly(data.period.date_from)} até {formatDateOnly(data.period.date_to)}
          </p>
        )}
      </div>

      <div className="mb-6">
        <ReportsFilters
          dateFrom={dateFrom}
          dateTo={dateTo}
          userId={userId}
          onFilterChange={updateParams}
          canFilterByUser={canFilterByUser}
        />
      </div>

      {isLoading && (
        <div className="flex justify-center py-16">
          <Spinner />
        </div>
      )}

      {!isLoading && isError && <ErrorState onRetry={refetch} />}

      {!isLoading && !isError && data && (
        <div className="flex flex-col gap-6">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard label="Leads criados" value={data.leads_created_total} />
            <MetricCard label="Leads convertidos" value={data.leads_converted_total} />
            <MetricCard label="Taxa de conversão" value={`${data.lead_conversion_rate}%`} />
            <MetricCard
              label="Tempo médio de conversão"
              value={data.avg_conversion_time_hours !== null ? `${data.avg_conversion_time_hours}h` : '—'}
            />
            <MetricCard label="Oportunidades criadas" value={data.opportunities_created_total} />
            <MetricCard label="Oportunidades ganhas" value={data.opportunities_won_total} />
            <MetricCard label="Oportunidades perdidas" value={data.opportunities_lost_total} />
            <MetricCard
              label="Tempo médio de fechamento"
              value={data.avg_closing_time_hours !== null ? `${data.avg_closing_time_hours}h` : '—'}
            />
            <MetricCard label="Valor ganho" value={formatCurrency(data.value_won)} />
            <MetricCard label="Valor perdido" value={formatCurrency(data.value_lost)} />
            <MetricCard label="Atividades criadas" value={data.activities_created_total} />
            <MetricCard label="Atividades concluídas" value={data.activities_completed_total} />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <DailyTrendChart
              title="Leads criados × convertidos"
              series={[
                { label: 'Criados', color: 'var(--color-primary)', data: data.daily_series.leads_created },
                { label: 'Convertidos', color: 'var(--color-success)', data: data.daily_series.leads_converted },
              ]}
            />
            <DailyTrendChart
              title="Oportunidades ganhas × perdidas"
              series={[
                { label: 'Ganhas', color: 'var(--color-success)', data: data.daily_series.opportunities_won },
                { label: 'Perdidas', color: 'var(--color-danger)', data: data.daily_series.opportunities_lost },
              ]}
            />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ActivitiesByTypeChart activitiesByType={data.activities_completed_by_type} />
            <LostReasonsList lostReasons={data.lost_reasons} />
          </div>

          <PerformanceByUserTable performanceByUser={data.performance_by_user} />

          <div>
            <p className="mb-2 text-xs text-muted">
              Snapshot atual do pipeline (não é histórico do período selecionado)
            </p>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              <PipelineByStageSummary stages={data.pipeline_snapshot.by_stage} />
              <MetricCard label="Valor em aberto (atual)" value={formatCurrency(data.pipeline_snapshot.open_value)} />
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
