export function SortableHeader({ label, active, order, onClick }) {
  return (
    <button type="button" onClick={onClick} className="inline-flex items-center gap-1 hover:text-text">
      {label}
      {active && (order === 'asc' ? '↑' : '↓')}
    </button>
  )
}
