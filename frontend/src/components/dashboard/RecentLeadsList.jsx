import { LeadStatusBadge } from '../leads/LeadStatusBadge'
import { formatDate } from '../../utils/formatters'

export function RecentLeadsList({ leads }) {
  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Leads recentes</h2>
      {leads.length === 0 ? (
        <p className="text-sm text-muted">Nenhum lead criado ainda.</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {leads.map((lead) => (
            <li key={lead.id} className="flex items-center justify-between gap-3 text-sm">
              <div>
                <p className="font-medium text-text">{lead.name}</p>
                <p className="text-muted">{lead.assigned_to?.name ?? '—'}</p>
              </div>
              <div className="text-right">
                <LeadStatusBadge status={lead.status} />
                <p className="mt-1 text-muted">{formatDate(lead.created_at)}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
