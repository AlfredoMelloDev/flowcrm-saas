import { Badge } from '../ui/Badge'
import { STATUS_LABEL, STATUS_TONE } from '../../utils/leadOptions'

export function LeadStatusBadge({ status }) {
  return <Badge tone={STATUS_TONE[status] ?? 'neutral'}>{STATUS_LABEL[status] ?? status}</Badge>
}
