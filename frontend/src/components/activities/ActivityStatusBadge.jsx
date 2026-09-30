import { Badge } from '../ui/Badge'
import { STATUS_LABEL, STATUS_TONE } from '../../utils/activityOptions'

// Takes the whole activity (not just its status) — "is_overdue" is a
// derived flag from the API, not a stored status, so a pending activity
// past its scheduled_at shows "Atrasada" here instead of "Pendente" without
// that ever being a real value this component (or the backend) persists.
export function ActivityStatusBadge({ activity }) {
  if (activity.is_overdue) {
    return <Badge tone="danger">Atrasada</Badge>
  }

  return <Badge tone={STATUS_TONE[activity.status] ?? 'neutral'}>{STATUS_LABEL[activity.status] ?? activity.status}</Badge>
}
