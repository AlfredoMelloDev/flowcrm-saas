import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { STATUS_OPTIONS, TYPE_OPTIONS } from '../../utils/clientOptions'

export function ClientFilters({ search, onSearchChange, status, type, onFilterChange }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
      <div className="flex-1">
        <Input
          id="client-search"
          label="Buscar"
          placeholder="Nome, e-mail, telefone ou documento"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
        />
      </div>

      <div className="w-full sm:w-48">
        <Select
          id="client-status-filter"
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
          id="client-type-filter"
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
    </div>
  )
}
