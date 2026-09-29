import { Badge } from '../ui/Badge'
import { OpportunityCard } from './OpportunityCard'
import { STAGE_LABEL, STAGE_TONE } from '../../utils/opportunityOptions'

export function OpportunityColumn({ stage, opportunities, onEdit, onDelete, canDelete }) {
  return (
    <div
      data-testid={`opportunity-column-${stage}`}
      className="flex w-72 shrink-0 flex-col gap-3 rounded-xl border border-border bg-background p-3 sm:w-80"
    >
      <div className="flex items-center justify-between">
        <Badge tone={STAGE_TONE[stage]}>{STAGE_LABEL[stage]}</Badge>
        <span className="text-xs font-medium text-muted">{opportunities.length}</span>
      </div>

      <div className="flex flex-col gap-3">
        {opportunities.length === 0 ? (
          <p className="rounded-lg border border-dashed border-border py-6 text-center text-xs text-muted">
            Nenhuma oportunidade
          </p>
        ) : (
          opportunities.map((opportunity) => (
            <OpportunityCard
              key={opportunity.id}
              opportunity={opportunity}
              onEdit={onEdit}
              onDelete={onDelete}
              canDelete={canDelete}
            />
          ))
        )}
      </div>
    </div>
  )
}
