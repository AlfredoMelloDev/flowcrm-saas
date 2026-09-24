import { Select } from '../ui/Select'
import { useAssignableUsers } from '../../hooks/useAssignableUsers'

/**
 * Shared by Lead and Client forms: renders the "Responsável" field only for
 * Admin/Manager, and only fetches /api/v1/users/assignable when it will
 * actually be shown — a Seller never needs this list.
 */
export function AssigneeSelect({ id, value, onChange, error, canAssign }) {
  const assignableUsers = useAssignableUsers(canAssign)

  if (!canAssign) {
    return null
  }

  return (
    <Select
      id={id}
      label="Responsável"
      value={value}
      onChange={onChange}
      error={error}
      disabled={assignableUsers.isLoading}
    >
      <option value="">Sem responsável</option>
      {assignableUsers.data?.map((assignableUser) => (
        <option key={assignableUser.id} value={assignableUser.id}>
          {assignableUser.name}
        </option>
      ))}
    </Select>
  )
}
