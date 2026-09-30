import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { useActivities } from '../hooks/useActivities'
import { useDeleteActivity } from '../hooks/useDeleteActivity'
import { useCompleteActivity } from '../hooks/useCompleteActivity'
import { useReopenActivity } from '../hooks/useReopenActivity'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { Button } from '../components/ui/Button'
import { Pagination } from '../components/ui/Pagination'
import { ActivityFilters } from '../components/activities/ActivityFilters'
import { ActivityTable } from '../components/activities/ActivityTable'
import { ActivityFormModal } from '../components/activities/ActivityFormModal'
import { WINDOW_OPTIONS } from '../utils/activityOptions'

export function ActivitiesPage() {
  const { role } = useAuth()
  const canManage = role === 'admin' || role === 'manager'

  const [searchParams, setSearchParams] = useSearchParams()
  const [searchInput, setSearchInput] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebouncedValue(searchInput)

  const type = searchParams.get('type') ?? ''
  const status = searchParams.get('status') ?? ''
  const activityWindow = searchParams.get('window') ?? ''
  const sort = searchParams.get('sort') ?? 'scheduled_at'
  const order = searchParams.get('order') ?? 'asc'
  const page = Number(searchParams.get('page') ?? '1')

  const [editingActivity, setEditingActivity] = useState(null)
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
    if (!('page' in next)) {
      params.delete('page')
    }
    setSearchParams(params)
  }

  const queryParams = {
    search: debouncedSearch || undefined,
    type: type || undefined,
    status: status || undefined,
    window: activityWindow || undefined,
    sort,
    order,
    page,
  }

  const { data, isLoading, isError, refetch } = useActivities(queryParams)
  const deleteActivity = useDeleteActivity()
  const completeActivity = useCompleteActivity()
  const reopenActivity = useReopenActivity()

  function handleDelete(activity) {
    if (window.confirm(`Excluir a atividade "${activity.title}"? Esta ação não pode ser desfeita.`)) {
      deleteActivity.mutate(activity.id)
    }
  }

  const hasFilters = Boolean(debouncedSearch || type || status || activityWindow)

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-text">Atividades</h1>
        <Button onClick={() => setCreating(true)}>Nova Atividade</Button>
      </div>

      <div className="mb-4 flex flex-wrap gap-2">
        {WINDOW_OPTIONS.map((option) => (
          <Button
            key={option.value}
            variant={activityWindow === option.value ? 'primary' : 'secondary'}
            onClick={() => updateParams({ window: option.value || undefined })}
          >
            {option.label}
          </Button>
        ))}
      </div>

      <div className="mb-4">
        <ActivityFilters
          search={searchInput}
          onSearchChange={(value) => {
            setSearchInput(value)
            updateParams({ search: value || undefined })
          }}
          type={type}
          status={status}
          onFilterChange={updateParams}
        />
      </div>

      <ActivityTable
        activities={data?.data ?? []}
        isLoading={isLoading}
        isError={isError}
        onRetry={refetch}
        sort={sort}
        order={order}
        onSortChange={(nextSort, nextOrder) => updateParams({ sort: nextSort, order: nextOrder })}
        onEdit={setEditingActivity}
        onDelete={handleDelete}
        onComplete={(activity) => completeActivity.mutate(activity.id)}
        onReopen={(activity) => reopenActivity.mutate(activity.id)}
        canDelete={canManage}
        hasFilters={hasFilters}
      />

      <Pagination
        meta={data?.meta}
        onPageChange={(nextPage) => updateParams({ page: String(nextPage) })}
      />

      {creating && <ActivityFormModal onClose={() => setCreating(false)} />}
      {editingActivity && (
        <ActivityFormModal activity={editingActivity} onClose={() => setEditingActivity(null)} />
      )}
    </div>
  )
}
