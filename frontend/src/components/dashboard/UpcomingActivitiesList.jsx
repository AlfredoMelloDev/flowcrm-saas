import { TYPE_LABEL } from '../../utils/activityOptions'
import { formatDateTime } from '../../utils/formatters'

export function UpcomingActivitiesList({ activities }) {
  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Próximas atividades</h2>
      {activities.length === 0 ? (
        <p className="text-sm text-muted">Nenhuma atividade próxima.</p>
      ) : (
        <ul className="flex flex-col gap-3">
          {activities.map((activity) => (
            <li key={activity.id} className="flex items-center justify-between gap-3 text-sm">
              <div>
                <p className="font-medium text-text">{activity.title}</p>
                <p className="text-muted">{TYPE_LABEL[activity.type] ?? activity.type}</p>
              </div>
              <div className="text-right">
                <p className="text-text">{activity.assigned_to.name}</p>
                <p className="text-muted">{formatDateTime(activity.scheduled_at)}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
