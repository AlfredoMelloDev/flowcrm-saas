// Mirrors the backend enums (App\Enums\ClientStatus / ClientType) — values
// must match exactly, since they're sent straight through to the API.
export const STATUS_OPTIONS = [
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
]

export const TYPE_OPTIONS = [
  { value: 'individual', label: 'Pessoa física' },
  { value: 'company', label: 'Pessoa jurídica' },
]

export const STATUS_TONE = {
  active: 'success',
  inactive: 'neutral',
}

export const STATUS_LABEL = Object.fromEntries(
  STATUS_OPTIONS.map((option) => [option.value, option.label]),
)

export const TYPE_LABEL = Object.fromEntries(
  TYPE_OPTIONS.map((option) => [option.value, option.label]),
)

export const SORTABLE_COLUMNS = [
  { value: 'name', label: 'Nome' },
  { value: 'status', label: 'Status' },
  { value: 'created_at', label: 'Criado em' },
]
