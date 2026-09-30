// Mirrors the backend enums (App\Enums\ActivityType / ActivityStatus) —
// values must match exactly, since they're sent straight through to the API.
export const TYPE_OPTIONS = [
  { value: 'call', label: 'Ligação' },
  { value: 'email', label: 'E-mail' },
  { value: 'meeting', label: 'Reunião' },
  { value: 'task', label: 'Tarefa' },
  { value: 'follow_up', label: 'Follow-up' },
]

// Deliberately just these two — "Atrasada" is never a real status value,
// only a derived display state (see ActivityStatusBadge).
export const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pendente' },
  { value: 'completed', label: 'Concluída' },
]

export const TYPE_LABEL = Object.fromEntries(TYPE_OPTIONS.map((option) => [option.value, option.label]))
export const STATUS_LABEL = Object.fromEntries(STATUS_OPTIONS.map((option) => [option.value, option.label]))

export const STATUS_TONE = {
  pending: 'info',
  completed: 'success',
}

export const SORTABLE_COLUMNS = [
  { value: 'title', label: 'Título' },
  { value: 'type', label: 'Tipo' },
  { value: 'status', label: 'Status' },
  { value: 'scheduled_at', label: 'Agendado para' },
  { value: 'created_at', label: 'Criado em' },
]

// Drives the "Relacionado a" picker in the form — at most one may be chosen,
// matching the backend's zero-or-exactly-one relation rule.
export const RELATION_TYPE_OPTIONS = [
  { value: '', label: 'Nenhum' },
  { value: 'lead', label: 'Lead' },
  { value: 'client', label: 'Cliente' },
  { value: 'opportunity', label: 'Oportunidade' },
]

// Drives the Hoje/Próximas/Atrasadas quick-filter tabs — each just sets this
// literal string as the "window" query param; the actual date comparison
// always happens server-side (see ActivityController::applyWindow).
export const WINDOW_OPTIONS = [
  { value: '', label: 'Todas' },
  { value: 'today', label: 'Hoje' },
  { value: 'upcoming', label: 'Próximas' },
  { value: 'overdue', label: 'Atrasadas' },
]
