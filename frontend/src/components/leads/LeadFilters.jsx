import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { STATUS_OPTIONS, SOURCE_OPTIONS } from '../../utils/leadOptions'

export function LeadFilters({ search, onSearchChange, status, source, onFilterChange }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
      <div className="flex-1">
        <Input
          id="lead-search"
          label="Buscar"
          placeholder="Nome, e-mail ou telefone"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
        />
      </div>

      <div className="w-full sm:w-48">
        <Select
          id="lead-status-filter"
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

      <div className="w-full sm:w-48">
        <Select
          id="lead-source-filter"
          label="Origem"
          value={source}
          onChange={(event) => onFilterChange({ source: event.target.value })}
        >
          <option value="">Todas</option>
          {SOURCE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      </div>
    </div>
  )
}
