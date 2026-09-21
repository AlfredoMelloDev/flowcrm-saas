export function Spinner({ className = '' }) {
  return (
    <div
      role="status"
      aria-label="Carregando"
      className={`h-5 w-5 animate-spin rounded-full border-2 border-border border-t-primary ${className}`}
    />
  )
}
