import { Spinner } from '../ui/Spinner'
import { ErrorState } from '../ui/ErrorState'
import { EmptyState } from '../ui/EmptyState'
import { Button } from '../ui/Button'
import { SortableHeader } from '../ui/SortableHeader'
import { ClientStatusBadge } from './ClientStatusBadge'
import { TYPE_LABEL } from '../../utils/clientOptions'
import { formatDate } from '../../utils/formatters'

const COLUMNS = [
  { key: 'name', label: 'Nome', sortable: true },
  { key: 'contact', label: 'Contato', sortable: false },
  { key: 'document', label: 'Documento', sortable: false },
  { key: 'type', label: 'Tipo', sortable: false },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'assigned_to', label: 'Responsável', sortable: false },
  { key: 'created_at', label: 'Criado em', sortable: true },
]

export function ClientTable({
  clients,
  isLoading,
  isError,
  onRetry,
  sort,
  order,
  onSortChange,
  onEdit,
  onDelete,
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

  if (clients.length === 0) {
    return (
      <EmptyState
        title={hasFilters ? 'Nenhum cliente encontrado' : 'Nenhum cliente ainda'}
        description={
          hasFilters
            ? 'Ajuste a busca ou os filtros para ver outros resultados.'
            : 'Quando um cliente for criado, ele aparecerá aqui.'
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
            {clients.map((client) => (
              <tr key={client.id} className="border-b border-border last:border-0">
                <td className="px-4 py-3 font-medium text-text">{client.name}</td>
                <td className="px-4 py-3 text-muted">
                  <div>{client.email ?? '—'}</div>
                  <div>{client.phone ?? ''}</div>
                </td>
                <td className="px-4 py-3 text-muted">{client.document ?? '—'}</td>
                <td className="px-4 py-3 text-muted">{TYPE_LABEL[client.type] ?? client.type}</td>
                <td className="px-4 py-3">
                  <ClientStatusBadge status={client.status} />
                </td>
                <td className="px-4 py-3 text-muted">{client.assigned_to?.name ?? '—'}</td>
                <td className="px-4 py-3 text-muted">{formatDate(client.created_at)}</td>
                <td className="px-4 py-3 text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => onEdit(client)}>
                      Editar
                    </Button>
                    {canDelete && (
                      <Button variant="ghost" onClick={() => onDelete(client)}>
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
        {clients.map((client) => (
          <div key={client.id} className="rounded-xl border border-border bg-surface p-4">
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="font-medium text-text">{client.name}</p>
                <p className="text-sm text-muted">{client.email ?? '—'}</p>
              </div>
              <ClientStatusBadge status={client.status} />
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-x-2 gap-y-1 text-sm">
              <dt className="text-muted">Documento</dt>
              <dd className="text-text">{client.document ?? '—'}</dd>
              <dt className="text-muted">Tipo</dt>
              <dd className="text-text">{TYPE_LABEL[client.type] ?? client.type}</dd>
              <dt className="text-muted">Responsável</dt>
              <dd className="text-text">{client.assigned_to?.name ?? '—'}</dd>
            </dl>
            <div className="mt-3 flex justify-end gap-2">
              <Button variant="ghost" onClick={() => onEdit(client)}>
                Editar
              </Button>
              {canDelete && (
                <Button variant="ghost" onClick={() => onDelete(client)}>
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
