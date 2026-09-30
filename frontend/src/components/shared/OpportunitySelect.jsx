import { Select } from '../ui/Select'
import { useOpportunityOptions } from '../../hooks/useOpportunityOptions'

/**
 * Mirrors ClientSelect: lists opportunities visible to the current user
 * (Admin/Manager see all, Seller sees only their own). "currentOpportunity"
 * covers editing an Activity whose Opportunity has since been soft-deleted.
 */
export function OpportunitySelect({ id, value, onChange, error, currentOpportunity }) {
  const opportunityOptions = useOpportunityOptions()
  const options = opportunityOptions.data ?? []
  const showCurrentOpportunity = currentOpportunity
    && !options.some((opportunity) => opportunity.id === currentOpportunity.id)

  return (
    <Select
      id={id}
      label="Oportunidade"
      value={value}
      onChange={onChange}
      error={error}
      disabled={opportunityOptions.isLoading}
    >
      <option value="">Selecione uma oportunidade</option>
      {showCurrentOpportunity && (
        <option value={currentOpportunity.id}>{currentOpportunity.title} (indisponível)</option>
      )}
      {options.map((opportunity) => (
        <option key={opportunity.id} value={opportunity.id}>
          {opportunity.title}
        </option>
      ))}
    </Select>
  )
}
