import { Select } from '../ui/Select'
import { useAssignableUsers } from '../../hooks/useAssignableUsers'

/**
 * Shared by Lead/Client/Opportunity/Activity forms: renders the
 * "Responsável" field only for Admin/Manager, and only fetches
 * /api/v1/users/assignable when it will actually be shown — a Seller never
 * needs this list.
 *
 * "required": Activity has a mandatory responsible user (unlike Lead/
 * Client/Opportunity, where it's optional) — passing this omits the "Sem
 * responsável" placeholder and marks the underlying <select> as required,
 * without duplicating this whole component for one field's difference.
 */
export function AssigneeSelect({ id, value, onChange, error, canAssign, required = false }) {
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
      required={required}
      disabled={assignableUsers.isLoading}
    >
      {!required && <option value="">Sem responsável</option>}
      {assignableUsers.data?.map((assignableUser) => (
        <option key={assignableUser.id} value={assignableUser.id}>
          {assignableUser.name}
        </option>
      ))}
    </Select>
  )
}
