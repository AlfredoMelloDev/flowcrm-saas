// Mirrors the backend enum (App\Enums\OpportunityStage) — values and order
// must match exactly, since they're sent straight through to the API and
// drive the pipeline's column order.
export const STAGE_OPTIONS = [
  { value: 'new', label: 'Novo' },
  { value: 'contacted', label: 'Contatado' },
  { value: 'proposal', label: 'Proposta' },
  { value: 'negotiation', label: 'Negociação' },
  { value: 'won', label: 'Ganho' },
  { value: 'lost', label: 'Perdido' },
]

export const STAGE_LABEL = Object.fromEntries(
  STAGE_OPTIONS.map((option) => [option.value, option.label]),
)

export const STAGE_TONE = {
  new: 'info',
  contacted: 'primary',
  proposal: 'primary',
  negotiation: 'warning',
  won: 'success',
  lost: 'danger',
}
