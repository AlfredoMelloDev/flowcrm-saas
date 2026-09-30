import { Select } from '../ui/Select'
import { useLeadOptions } from '../../hooks/useLeadOptions'

/**
 * Mirrors ClientSelect: lists leads visible to the current user (Admin/
 * Manager see all, Seller sees only their own). "currentLead" covers
 * editing an Activity whose Lead has since been soft-deleted — without it,
 * the <select> would show blank instead of the lead that's actually related.
 */
export function LeadSelect({ id, value, onChange, error, currentLead }) {
  const leadOptions = useLeadOptions()
  const options = leadOptions.data ?? []
  const showCurrentLead = currentLead && !options.some((lead) => lead.id === currentLead.id)

  return (
    <Select
      id={id}
      label="Lead"
      value={value}
      onChange={onChange}
      error={error}
      disabled={leadOptions.isLoading}
    >
      <option value="">Selecione um lead</option>
      {showCurrentLead && <option value={currentLead.id}>{currentLead.name} (indisponível)</option>}
      {options.map((lead) => (
        <option key={lead.id} value={lead.id}>
          {lead.name}
        </option>
      ))}
    </Select>
  )
}
