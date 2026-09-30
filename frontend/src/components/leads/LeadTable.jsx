import { Spinner } from '../ui/Spinner'
import { ErrorState } from '../ui/ErrorState'
import { EmptyState } from '../ui/EmptyState'
import { Button } from '../ui/Button'
import { SortableHeader } from '../ui/SortableHeader'
import { LeadStatusBadge } from './LeadStatusBadge'
import { SOURCE_LABEL } from '../../utils/leadOptions'
import { formatCurrency, formatDate } from '../../utils/formatters'

const COLUMNS = [
  { key: 'name', label: 'Nome', sortable: true },
  { key: 'contact', label: 'Contato', sortable: false },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'source', label: 'Origem', sortable: false },
  { key: 'assigned_to', label: 'Responsável', sortable: false },
  { key: 'estimated_value', label: 'Valor estimado', sortable: true },
  { key: 'created_at', label: 'Criado em', sortable: true },
]

export function LeadTable({
  leads,
  isLoading,
  isError,
  onRetry,
  sort,
  order,
  onSortChange,
  onEdit,
  onDelete,
  onConvert,
  canDelete,
  canConvert,
  hasFilters,
}) {
  if (isLoading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner />
      </div>
    )
  }

  if (isError) {
    return <ErrorState onRetry={onRetry} />
  }

  if (leads.length === 0) {
    return (
      <EmptyState
        title={hasFilters ? 'Nenhum lead encontrado' : 'Nenhum lead ainda'}
        description={
          hasFilters
            ? 'Ajuste a busca ou os filtros para ver outros resultados.'
            : 'Quando um lead for criado, ele aparecerá aqui.'
        }
      />
    )
  }

  function toggleSort(column) {
    if (sort === column) {
      onSortChange(column, order === 'asc' ? 'desc' : 'asc')
    } else {
      onSortChange(column, 'asc')
    }
  }

  return (
    <>
      {/* Desktop / tablet: table. */}
      <div className="hidden overflow-x-auto rounded-xl border border-border bg-surface sm:block">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-border text-xs uppercase text-muted">
            <tr>
              {COLUMNS.map((column) => (
                <th key={column.key} className="px-4 py-3 font-medium">
                  {column.sortable ? (
                    <SortableHeader
                      label={column.label}
                      active={sort === column.key}
                      order={order}
                      onClick={() => toggleSort(column.key)}
                    />
                  ) : (
                    column.label
                  )}
                </th>
              ))}
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {leads.map((lead) => (
              <tr key={lead.id} className="border-b border-border last:border-0">
                <td className="px-4 py-3 font-medium text-text">{lead.name}</td>
                <td className="px-4 py-3 text-muted">
                  <div>{lead.email ?? '—'}</div>
                  <div>{lead.phone ?? ''}</div>
                </td>
                <td className="px-4 py-3">
                  <LeadStatusBadge status={lead.status} />
                </td>
                <td className="px-4 py-3 text-muted">
                  {lead.source ? SOURCE_LABEL[lead.source] ?? lead.source : '—'}
                </td>
                <td className="px-4 py-3 text-muted">{lead.assigned_to?.name ?? '—'}</td>
                <td className="px-4 py-3 text-muted">{formatCurrency(lead.estimated_value)}</td>
                <td className="px-4 py-3 text-muted">{formatDate(lead.created_at)}</td>
                <td className="px-4 py-3 text-right">
                  <div className="flex justify-end gap-2">
                    {canConvert?.(lead) && (
                      <Button variant="ghost" onClick={() => onConvert(lead)}>
                        Converter
                      </Button>
                    )}
                    <Button variant="ghost" onClick={() => onEdit(lead)}>
                      Editar
                    </Button>
                    {canDelete && (
                      <Button variant="ghost" onClick={() => onDelete(lead)}>
                        Excluir
                      </Button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Mobile: stacked cards. */}
      <div className="flex flex-col gap-3 sm:hidden">
        {leads.map((lead) => (
          <div key={lead.id} className="rounded-xl border border-border bg-surface p-4">
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="font-medium text-text">{lead.name}</p>
                <p className="text-sm text-muted">{lead.email ?? '—'}</p>
              </div>
              <LeadStatusBadge status={lead.status} />
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-x-2 gap-y-1 text-sm">
              <dt className="text-muted">Origem</dt>
              <dd className="text-text">
                {lead.source ? SOURCE_LABEL[lead.source] ?? lead.source : '—'}
              </dd>
              <dt className="text-muted">Responsável</dt>
              <dd className="text-text">{lead.assigned_to?.name ?? '—'}</dd>
              <dt className="text-muted">Valor estimado</dt>
              <dd className="text-text">{formatCurrency(lead.estimated_value)}</dd>
            </dl>
            <div className="mt-3 flex justify-end gap-2">
              {canConvert?.(lead) && (
                <Button variant="ghost" onClick={() => onConvert(lead)}>
                  Converter
                </Button>
              )}
              <Button variant="ghost" onClick={() => onEdit(lead)}>
                Editar
              </Button>
              {canDelete && (
                <Button variant="ghost" onClick={() => onDelete(lead)}>
                  Excluir
                </Button>
              )}
            </div>
          </div>
        ))}
      </div>
    </>
  )
}
