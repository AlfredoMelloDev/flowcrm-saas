import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useOpportunityPipeline } from '../hooks/useOpportunityPipeline'
import { useDeleteOpportunity } from '../hooks/useDeleteOpportunity'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { Button } from '../components/ui/Button'
import { Input } from '../components/ui/Input'
import { Spinner } from '../components/ui/Spinner'
import { ErrorState } from '../components/ui/ErrorState'
import { AssigneeSelect } from '../components/shared/AssigneeSelect'
import { OpportunityColumn } from '../components/opportunities/OpportunityColumn'
import { OpportunityFormModal } from '../components/opportunities/OpportunityFormModal'
import { STAGE_OPTIONS } from '../utils/opportunityOptions'

export function OpportunitiesPage() {
  const { role } = useAuth()
  const canManage = role === 'admin' || role === 'manager'

  const [searchParams, setSearchParams] = useSearchParams()
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebouncedValue(searchInput)
  const userId = searchParams.get('user_id') ?? ''

  const [editingOpportunity, setEditingOpportunity] = useState(null)
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
    setSearchParams(params)
  }

  const queryParams = {
    search: debouncedSearch || undefined,
    user_id: userId || undefined,
  }

  const { data, isLoading, isError, refetch } = useOpportunityPipeline(queryParams)
  const deleteOpportunity = useDeleteOpportunity()

  function handleDelete(opportunity) {
    if (window.confirm(`Excluir a oportunidade "${opportunity.title}"? Esta ação não pode ser desfeita.`)) {
      deleteOpportunity.mutate(opportunity.id)
    }
  }

  const opportunities = data ?? []

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-text">Pipeline</h1>
        <Button onClick={() => setCreating(true)}>Nova Oportunidade</Button>
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div className="flex-1">
          <Input
            id="opportunity-search"
            label="Buscar"
            placeholder="Título ou cliente"
            value={searchInput}
            onChange={(event) => {
              setSearchInput(event.target.value)
              updateParams({ search: event.target.value || undefined })
            }}
          />
        </div>

        <div className="w-full sm:w-56">
          <AssigneeSelect
            id="opportunity-user-filter"
            value={userId}
            onChange={(event) => updateParams({ user_id: event.target.value })}
            canAssign={canManage}
          />
        </div>
      </div>

      {isLoading && (
        <div className="flex justify-center py-16">
          <Spinner />
        </div>
      )}

      {!isLoading && isError && <ErrorState onRetry={refetch} />}

      {!isLoading && !isError && (
        <div className="flex gap-4 overflow-x-auto pb-2">
          {STAGE_OPTIONS.map((option) => (
            <OpportunityColumn
              key={option.value}
              stage={option.value}
              opportunities={opportunities.filter((opportunity) => opportunity.stage === option.value)}
              onEdit={setEditingOpportunity}
              onDelete={handleDelete}
              canDelete={canManage}
            />
          ))}
        </div>
      )}

      {creating && <OpportunityFormModal onClose={() => setCreating(false)} />}
      {editingOpportunity && (
        <OpportunityFormModal
          opportunity={editingOpportunity}
          onClose={() => setEditingOpportunity(null)}
        />
      )}
    </div>
  )
}
