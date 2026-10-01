import { Input } from '../ui/Input'
import { AssigneeSelect } from '../shared/AssigneeSelect'

export function ReportsFilters({ dateFrom, dateTo, userId, onFilterChange, canFilterByUser }) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
      <div className="w-full sm:w-48">
        <Input
          id="reports-date-from"
          label="De"
          type="date"
          value={dateFrom}
          onChange={(event) => onFilterChange({ date_from: event.target.value })}
        />
      </div>

      <div className="w-full sm:w-48">
        <Input
          id="reports-date-to"
          label="Até"
          type="date"
          value={dateTo}
          onChange={(event) => onFilterChange({ date_to: event.target.value })}
        />
      </div>

      <div className="w-full sm:w-56">
        <AssigneeSelect
          id="reports-user-filter"
          value={userId}
          onChange={(event) => onFilterChange({ user_id: event.target.value })}
          canAssign={canFilterByUser}
        />
      </div>
    </div>
  )
}
