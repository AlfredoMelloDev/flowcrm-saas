// Mirrors the backend enums (App\Enums\LeadStatus / LeadSource) — values must
// match exactly, since they're sent straight through to the API.
export const STATUS_OPTIONS = [
  { value: 'new', label: 'Novo' },
  { value: 'contacted', label: 'Contatado' },
  { value: 'qualified', label: 'Qualificado' },
  { value: 'unqualified', label: 'Desqualificado' },
  { value: 'converted', label: 'Convertido' },
]

export const SOURCE_OPTIONS = [
  { value: 'website', label: 'Site' },
  { value: 'referral', label: 'Indicação' },
  { value: 'social_media', label: 'Redes sociais' },
  { value: 'email', label: 'E-mail' },
  { value: 'phone', label: 'Telefone' },
  { value: 'advertisement', label: 'Anúncio' },
  { value: 'event', label: 'Evento' },
  { value: 'outbound', label: 'Prospecção ativa' },
  { value: 'other', label: 'Outro' },
]

// A Lead only ever reaches "converted" through POST /leads/{lead}/convert —
// the plain status <Select> on the edit form must never offer it as a
// manual target (the backend rejects it anyway, but the UI shouldn't imply
// it's a normal option in the first place).
export const EDITABLE_STATUS_OPTIONS = STATUS_OPTIONS.filter((option) => option.value !== 'converted')

export const STATUS_TONE = {
  new: 'info',
  contacted: 'primary',
  qualified: 'success',
  unqualified: 'neutral',
  converted: 'success',
}

export const STATUS_LABEL = Object.fromEntries(
  STATUS_OPTIONS.map((option) => [option.value, option.label]),
)

export const SOURCE_LABEL = Object.fromEntries(
  SOURCE_OPTIONS.map((option) => [option.value, option.label]),
)

export const SORTABLE_COLUMNS = [
  { value: 'name', label: 'Nome' },
  { value: 'status', label: 'Status' },
  { value: 'estimated_value', label: 'Valor estimado' },
  { value: 'created_at', label: 'Criado em' },
]
