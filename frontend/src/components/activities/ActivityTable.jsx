import { Spinner } from '../ui/Spinner'
import { ErrorState } from '../ui/ErrorState'
import { EmptyState } from '../ui/EmptyState'
import { Button } from '../ui/Button'
import { SortableHeader } from '../ui/SortableHeader'
import { ActivityStatusBadge } from './ActivityStatusBadge'
import { TYPE_LABEL } from '../../utils/activityOptions'
import { formatDateTime } from '../../utils/formatters'

const COLUMNS = [
  { key: 'title', label: 'Título', sortable: true },
  { key: 'type', label: 'Tipo', sortable: true },
  { key: 'related', label: 'Relacionado a', sortable: false },
  { key: 'assigned_to', label: 'Responsável', sortable: false },
  { key: 'scheduled_at', label: 'Agendado para', sortable: true },
  { key: 'status', label: 'Status', sortable: true },
]

function relatedLabel(activity) {
  if (activity.lead) {
    return `Lead: ${activity.lead.name}`
  }
  if (activity.client) {
    return `Cliente: ${activity.client.name}`
  }
  if (activity.opportunity) {
    return `Oportunidade: ${activity.opportunity.title}`
  }
  return '—'
}

export function ActivityTable({
  activities,
  isLoading,
  isError,
  onRetry,
  sort,
  order,
  onSortChange,
  onEdit,
  onDelete,
  onComplete,
  onReopen,
  canDelete,
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

  if (activities.length === 0) {
    return (
      <EmptyState
        title={hasFilters ? 'Nenhuma atividade encontrada' : 'Nenhuma atividade ainda'}
        description={
          hasFilters
            ? 'Ajuste a busca ou os filtros para ver outros resultados.'
            : 'Quando uma atividade for criada, ela aparecerá aqui.'
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
            {activities.map((activity) => (
              <tr key={activity.id} className="border-b border-border last:border-0">
                <td className="px-4 py-3 font-medium text-text">{activity.title}</td>
                <td className="px-4 py-3 text-muted">{TYPE_LABEL[activity.type] ?? activity.type}</td>
                <td className="px-4 py-3 text-muted">{relatedLabel(activity)}</td>
                <td className="px-4 py-3 text-muted">{activity.assigned_to.name}</td>
                <td className="px-4 py-3 text-muted">{formatDateTime(activity.scheduled_at)}</td>
                <td className="px-4 py-3">
                  <ActivityStatusBadge activity={activity} />
                </td>
                <td className="px-4 py-3 text-right">
                  <div className="flex justify-end gap-2">
                    {activity.status === 'pending' ? (
                      <Button variant="ghost" onClick={() => onComplete(activity)}>
                        Concluir
                      </Button>
                    ) : (
                      <Button variant="ghost" onClick={() => onReopen(activity)}>
                        Reabrir
                      </Button>
                    )}
                    <Button variant="ghost" onClick={() => onEdit(activity)}>
                      Editar
                    </Button>
                    {canDelete && (
                      <Button variant="ghost" onClick={() => onDelete(activity)}>
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
        {activities.map((activity) => (
          <div key={activity.id} className="rounded-xl border border-border bg-surface p-4">
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="font-medium text-text">{activity.title}</p>
                <p className="text-sm text-muted">{TYPE_LABEL[activity.type] ?? activity.type}</p>
              </div>
              <ActivityStatusBadge activity={activity} />
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-x-2 gap-y-1 text-sm">
              <dt className="text-muted">Relacionado a</dt>
              <dd className="text-text">{relatedLabel(activity)}</dd>
              <dt className="text-muted">Responsável</dt>
              <dd className="text-text">{activity.assigned_to.name}</dd>
              <dt className="text-muted">Agendado para</dt>
              <dd className="text-text">{formatDateTime(activity.scheduled_at)}</dd>
            </dl>
            <div className="mt-3 flex justify-end gap-2">
              {activity.status === 'pending' ? (
                <Button variant="ghost" onClick={() => onComplete(activity)}>
                  Concluir
                </Button>
              ) : (
                <Button variant="ghost" onClick={() => onReopen(activity)}>
                  Reabrir
                </Button>
              )}
              <Button variant="ghost" onClick={() => onEdit(activity)}>
                Editar
              </Button>
              {canDelete && (
                <Button variant="ghost" onClick={() => onDelete(activity)}>
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
