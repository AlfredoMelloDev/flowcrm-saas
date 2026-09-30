import { formatCurrency, formatDateOnly } from '../../utils/formatters'

export function ClosingSoonList({ opportunities }) {
  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Fechamento próximo</h2>
      {opportunities.length === 0 ? (
        <p className="text-sm text-muted">Nenhuma oportunidade com fechamento próximo.</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {opportunities.map((opportunity) => (
            <li key={opportunity.id} className="flex items-center justify-between gap-3 text-sm">
              <div>
                <p className="font-medium text-text">{opportunity.title}</p>
                <p className="text-muted">{opportunity.client?.name ?? '—'}</p>
              </div>
              <div className="text-right">
                <p className="text-text">{formatCurrency(opportunity.value)}</p>
                <p className="text-muted">{formatDateOnly(opportunity.expected_close_date)}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
