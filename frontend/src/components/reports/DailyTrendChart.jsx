import { formatDateOnly } from '../../utils/formatters'

const WIDTH = 600
const HEIGHT = 160
const PADDING = 8

// Small hand-rolled SVG line chart — no charting library, matching the
// project's existing "CSS/SVG only" precedent (see PipelineByStageSummary).
// Takes exactly the series it's given; not a generic reusable charting API.
export function DailyTrendChart({ title, series }) {
  const points = series[0]?.data ?? []

  if (points.length === 0) {
    return (
      <div className="rounded-xl border border-border bg-surface p-4">
        <h2 className="mb-3 text-sm font-semibold text-text">{title}</h2>
        <p className="text-sm text-muted">Sem dados no período selecionado.</p>
      </div>
    )
  }

  const maxCount = Math.max(1, ...series.flatMap((line) => line.data.map((point) => point.count)))
  const stepX = points.length > 1 ? (WIDTH - PADDING * 2) / (points.length - 1) : 0

  function toPolylinePoints(data) {
    return data
      .map((point, index) => {
        const x = PADDING + index * stepX
        const y = HEIGHT - PADDING - (point.count / maxCount) * (HEIGHT - PADDING * 2)
        return `${x},${y}`
      })
      .join(' ')
  }

  const firstDate = points[0]?.date
  const middleDate = points[Math.floor((points.length - 1) / 2)]?.date
  const lastDate = points[points.length - 1]?.date

  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-text">{title}</h2>
        <div className="flex gap-3 text-xs text-muted">
          {series.map((line) => (
            <span key={line.label} className="flex items-center gap-1">
              <span className="h-2 w-2 rounded-full" style={{ backgroundColor: line.color }} />
              {line.label}
            </span>
          ))}
        </div>
      </div>

      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="w-full" preserveAspectRatio="none" role="img">
        {series.map((line) => (
          <polyline
            key={line.label}
            points={toPolylinePoints(line.data)}
            fill="none"
            stroke={line.color}
            strokeWidth="2"
            strokeLinejoin="round"
            strokeLinecap="round"
          />
        ))}
      </svg>

      <div className="mt-1 flex justify-between text-xs text-muted">
        <span>{formatDateOnly(firstDate)}</span>
        {points.length > 2 && <span>{formatDateOnly(middleDate)}</span>}
        <span>{formatDateOnly(lastDate)}</span>
      </div>
    </div>
  )
}
