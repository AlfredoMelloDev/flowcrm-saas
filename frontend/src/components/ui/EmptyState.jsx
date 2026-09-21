export function EmptyState({ title, description }) {
  return (
    <div className="flex flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-border py-16 text-center">
      <p className="text-sm font-medium text-text">{title}</p>
      {description && <p className="text-sm text-muted">{description}</p>}
    </div>
  )
}
