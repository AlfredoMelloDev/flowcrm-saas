import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { TYPE_OPTIONS, STATUS_OPTIONS } from '../../utils/activityOptions'

export function ActivityFilters({ search, onSearchChange, type, status, onFilterChange }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
      <div className="flex-1">
        <Input
          id="activity-search"
          label="Buscar"
          placeholder="Título ou descrição"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
        />
      </div>

      <div className="w-full sm:w-48">
        <Select
          id="activity-type-filter"
          label="Tipo"
          value={type}
          onChange={(event) => onFilterChange({ type: event.target.value })}
        >
          <option value="">Todos</option>
          {TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      </div>

      <div className="w-full sm:w-48">
        <Select
          id="activity-status-filter"
          label="Status"
          value={status}
          onChange={(event) => onFilterChange({ status: event.target.value })}
        >
          <option value="">Todos</option>
          {STATUS_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      </div>
    </div>
  )
}
