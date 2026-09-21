import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useLeads } from '../hooks/useLeads'
import { useDeleteLead } from '../hooks/useDeleteLead'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { Button } from '../components/ui/Button'
import { LeadFilters } from '../components/leads/LeadFilters'
import { LeadTable } from '../components/leads/LeadTable'
import { LeadPagination } from '../components/leads/LeadPagination'
import { LeadFormModal } from '../components/leads/LeadFormModal'

export function LeadsPage() {
  const { role } = useAuth()
  const canManage = role === 'admin' || role === 'manager'

  const [searchParams, setSearchParams] = useSearchParams()
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebouncedValue(searchInput)

  const status = searchParams.get('status') ?? ''
  const source = searchParams.get('source') ?? ''
  const sort = searchParams.get('sort') ?? 'created_at'
  const order = searchParams.get('order') ?? 'desc'
  const page = Number(searchParams.get('page') ?? '1')

  const [editingLead, setEditingLead] = useState(null)
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
    source: source || undefined,
    sort,
    order,
    page,
  }

  const { data, isLoading, isError, refetch } = useLeads(queryParams)
  const deleteLead = useDeleteLead()

  function handleDelete(lead) {
    if (window.confirm(`Excluir o lead "${lead.name}"? Esta ação não pode ser desfeita.`)) {
      deleteLead.mutate(lead.id)
    }
  }

  const hasFilters = Boolean(debouncedSearch || status || source)

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-text">Leads</h1>
        <Button onClick={() => setCreating(true)}>Novo Lead</Button>
      </div>

      <div className="mb-4">
        <LeadFilters
          search={searchInput}
          onSearchChange={(value) => {
            setSearchInput(value)
            updateParams({ search: value || undefined })
          }}
          status={status}
          source={source}
          onFilterChange={updateParams}
        />
      </div>

      <LeadTable
        leads={data?.data ?? []}
        isLoading={isLoading}
        isError={isError}
        onRetry={refetch}
        sort={sort}
        order={order}
        onSortChange={(nextSort, nextOrder) => updateParams({ sort: nextSort, order: nextOrder })}
        onEdit={setEditingLead}
        onDelete={handleDelete}
        canDelete={canManage}
        hasFilters={hasFilters}
      />

      <LeadPagination
        meta={data?.meta}
        onPageChange={(nextPage) => updateParams({ page: String(nextPage) })}
      />

      {creating && <LeadFormModal onClose={() => setCreating(false)} />}
      {editingLead && (
        <LeadFormModal lead={editingLead} onClose={() => setEditingLead(null)} />
      )}
    </div>
  )
}
