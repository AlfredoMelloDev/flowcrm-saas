import { Badge } from '../ui/Badge'
import { STAGE_LABEL, STAGE_TONE } from '../../utils/opportunityOptions'
import { formatCurrency } from '../../utils/formatters'

// No chart library — a simple table with a CSS-only bar (width as a
// percentage of the busiest stage) is enough for this first version.
export function PipelineByStageSummary({ stages }) {
  const maxCount = Math.max(1, ...stages.map((stage) => stage.count))

  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Pipeline por estágio</h2>
      <div className="flex flex-col gap-3">
        {stages.map((row) => (
          <div key={row.stage} className="flex flex-col gap-1">
            <div className="flex items-center justify-between text-sm">
              <Badge tone={STAGE_TONE[row.stage] ?? 'neutral'}>
                {STAGE_LABEL[row.stage] ?? row.stage}
              </Badge>
              <span className="text-muted">
                {row.count} · {formatCurrency(row.value)}
              </span>
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
    </div>
  )
}
