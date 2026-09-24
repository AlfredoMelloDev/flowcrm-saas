import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useClients } from '../hooks/useClients'
import { useDeleteClient } from '../hooks/useDeleteClient'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { Button } from '../components/ui/Button'
import { Pagination } from '../components/ui/Pagination'
import { ClientFilters } from '../components/clients/ClientFilters'
import { ClientTable } from '../components/clients/ClientTable'
import { ClientFormModal } from '../components/clients/ClientFormModal'

export function ClientsPage() {
  const { role } = useAuth()
  const canManage = role === 'admin' || role === 'manager'

  const [searchParams, setSearchParams] = useSearchParams()
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebouncedValue(searchInput)

  const status = searchParams.get('status') ?? ''
  const type = searchParams.get('type') ?? ''
  const sort = searchParams.get('sort') ?? 'created_at'
  const order = searchParams.get('order') ?? 'desc'
  const page = Number(searchParams.get('page') ?? '1')

  const [editingClient, setEditingClient] = useState(null)
  const [creating, setCreating] = useState(false)

  function updateParams(next) {
    const params = new URLSearchParams(searchParams)
    for (const [key, value] of Object.entries(next)) {
      if (value) {
        params.set(key, value)
      } else {
        params.delete(key)
      }
    }
    // Any filter/search/sort change restarts pagination at page 1.
    if (!('page' in next)) {
      params.delete('page')
    }
    setSearchParams(params)
  }

  const queryParams = {
    search: debouncedSearch || undefined,
    status: status || undefined,
    type: type || undefined,
    sort,
    order,
    page,
  }

  const { data, isLoading, isError, refetch } = useClients(queryParams)
  const deleteClient = useDeleteClient()

  function handleDelete(client) {
    if (window.confirm(`Excluir o cliente "${client.name}"? Esta ação não pode ser desfeita.`)) {
      deleteClient.mutate(client.id)
    }
  }

  const hasFilters = Boolean(debouncedSearch || status || type)

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-text">Clientes</h1>
        <Button onClick={() => setCreating(true)}>Novo Cliente</Button>
      </div>

      <div className="mb-4">
        <ClientFilters
          search={searchInput}
          onSearchChange={(value) => {
            setSearchInput(value)
            updateParams({ search: value || undefined })
          }}
          status={status}
          type={type}
          onFilterChange={updateParams}
        />
      </div>

      <ClientTable
        clients={data?.data ?? []}
        isLoading={isLoading}
        isError={isError}
        onRetry={refetch}
        sort={sort}
        order={order}
        onSortChange={(nextSort, nextOrder) => updateParams({ sort: nextSort, order: nextOrder })}
        onEdit={setEditingClient}
        onDelete={handleDelete}
        canDelete={canManage}
        hasFilters={hasFilters}
      />

      <Pagination
        meta={data?.meta}
        onPageChange={(nextPage) => updateParams({ page: String(nextPage) })}
      />

      {creating && <ClientFormModal onClose={() => setCreating(false)} />}
      {editingClient && (
        <ClientFormModal client={editingClient} onClose={() => setEditingClient(null)} />
      )}
    </div>
  )
}
