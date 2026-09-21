import { Button } from '../ui/Button'

export function LeadPagination({ meta, onPageChange }) {
  if (!meta || meta.last_page <= 1) {
    return null
  }

  return (
    <div className="flex items-center justify-between border-t border-border px-1 py-3">
      <p className="text-sm text-muted">
        {meta.total === 0
          ? 'Nenhum resultado'
          : `Mostrando ${meta.from}–${meta.to} de ${meta.total}`}
      </p>
      <div className="flex gap-2">
        <Button
          variant="secondary"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          Anterior
        </Button>
        <Button
          variant="secondary"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Próxima
        </Button>
      </div>
    </div>
  )
}
