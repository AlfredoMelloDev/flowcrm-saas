// Same CSS-bar approach as PipelineByStageSummary — no chart library.
// "reason" is free text (Opportunity.lost_reason has no fixed set of
// values), so two differently-worded entries for the "same" reason are
// grouped as distinct rows — a data-entry consistency caveat, not a bug.
export function LostReasonsList({ lostReasons }) {
  const maxCount = Math.max(1, ...lostReasons.map((row) => row.count))

  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Motivos de perda</h2>
      {lostReasons.length === 0 ? (
        <p className="text-sm text-muted">Nenhuma oportunidade perdida com motivo registrado no período.</p>
      ) : (
        <div className="flex flex-col gap-3">
          {lostReasons.map((row) => (
            <div key={row.reason} className="flex flex-col gap-1">
              <div className="flex items-center justify-between text-sm">
                <span className="text-text">{row.reason}</span>
                <span className="text-muted">{row.count}</span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-background">
                <div
                  className="h-full rounded-full bg-danger/60"
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
