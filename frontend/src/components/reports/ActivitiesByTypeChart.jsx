import { TYPE_LABEL } from '../../utils/activityOptions'

// Same CSS-bar approach as PipelineByStageSummary — no chart library.
export function ActivitiesByTypeChart({ activitiesByType }) {
  const maxCount = Math.max(1, ...activitiesByType.map((row) => row.count))

  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Atividades concluídas por tipo</h2>
      {activitiesByType.length === 0 ? (
        <p className="text-sm text-muted">Nenhuma atividade concluída no período.</p>
      ) : (
        <div className="flex flex-col gap-3">
          {activitiesByType.map((row) => (
            <div key={row.type} className="flex flex-col gap-1">
              <div className="flex items-center justify-between text-sm">
                <span className="text-text">{TYPE_LABEL[row.type] ?? row.type}</span>
                <span className="text-muted">{row.count}</span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-background">
                <div
                  className="h-full rounded-full bg-primary/60"
                  style={{ width: `${(row.count / maxCount) * 100}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
