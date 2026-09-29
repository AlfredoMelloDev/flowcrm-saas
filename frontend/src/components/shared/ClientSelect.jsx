import { Select } from '../ui/Select'
import { useClientOptions } from '../../hooks/useClientOptions'

/**
 * Shared by the Opportunity form: lists only active clients visible to the
 * current user (Admin/Manager see all, Seller sees only their own — same
 * scoping /clients/options already applies server-side).
 *
 * "currentClient" covers editing an Opportunity whose client has since gone
 * inactive: /clients/options would no longer include it, so without this the
 * <select> would show blank instead of the client that's actually assigned,
 * even though nothing is actually wrong with the stored client_id.
 */
export function ClientSelect({ id, value, onChange, error, required, currentClient }) {
  const clientOptions = useClientOptions()
  const options = clientOptions.data ?? []
  const showCurrentClient = currentClient && !options.some((client) => client.id === currentClient.id)

  return (
    <Select
      id={id}
      label="Cliente"
      value={value}
      onChange={onChange}
      error={error}
      required={required}
      disabled={clientOptions.isLoading}
    >
      <option value="">Selecione um cliente</option>
      {showCurrentClient && (
        <option value={currentClient.id}>{currentClient.name} (inativo)</option>
      )}
      {options.map((client) => (
        <option key={client.id} value={client.id}>
          {client.name}
        </option>
      ))}
    </Select>
  )
}
