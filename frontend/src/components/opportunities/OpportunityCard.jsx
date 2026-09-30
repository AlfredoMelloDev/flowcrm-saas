import { useState } from 'react'
import { Button } from '../ui/Button'
import { Select } from '../ui/Select'
import { LostReasonModal } from './LostReasonModal'
import { useUpdateOpportunityStage } from '../../hooks/useUpdateOpportunityStage'
import { STAGE_OPTIONS } from '../../utils/opportunityOptions'
import { formatCurrency, formatDateOnly } from '../../utils/formatters'

export function OpportunityCard({ opportunity, onEdit, onDelete, canDelete }) {
  const updateStage = useUpdateOpportunityStage()
  const [pendingLostReason, setPendingLostReason] = useState(false)

  function handleStageChange(event) {
    const nextStage = event.target.value

    // LOST always needs a reason first — the card must not move until the
    // modal is confirmed, so the mutation simply isn't fired yet here.
    if (nextStage === 'lost') {
      setPendingLostReason(true)
      return
    }

    updateStage.mutate({ id: opportunity.id, payload: { stage: nextStage } })
  }

  function handleConfirmLost(reason) {
    updateStage.mutate(
      { id: opportunity.id, payload: { stage: 'lost', lost_reason: reason } },
      { onSuccess: () => setPendingLostReason(false) },
    )
  }

  return (
    <div className="rounded-xl border border-border bg-surface p-3 shadow-sm">
      <p className="font-medium text-text">{opportunity.title}</p>
      <p className="text-sm text-muted">{opportunity.client?.name ?? '—'}</p>

      <dl className="mt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
        <dt className="text-muted">Valor</dt>
        <dd className="text-text">{formatCurrency(opportunity.value)}</dd>
        <dt className="text-muted">Responsável</dt>
        <dd className="text-text">{opportunity.assigned_to?.name ?? '—'}</dd>
        {opportunity.expected_close_date && (
          <>
            <dt className="text-muted">Previsão</dt>
            <dd className="text-text">{formatDateOnly(opportunity.expected_close_date)}</dd>
          </>
        )}
      </dl>

      <div className="mt-3">
        <Select
          id={`opportunity-stage-${opportunity.id}`}
          label="Estágio"
          value={opportunity.stage}
          onChange={handleStageChange}
          disabled={updateStage.isPending}
        >
          {STAGE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      </div>

      <div className="mt-3 flex justify-end gap-2">
        <Button variant="ghost" onClick={() => onEdit(opportunity)}>
          Editar
        </Button>
        {canDelete && (
          <Button variant="ghost" onClick={() => onDelete(opportunity)}>
            Excluir
          </Button>
        )}
      </div>

      {pendingLostReason && (
        <LostReasonModal
          onConfirm={handleConfirmLost}
          onClose={() => setPendingLostReason(false)}
          isSubmitting={updateStage.isPending}
        />
      )}
    </div>
  )
}
