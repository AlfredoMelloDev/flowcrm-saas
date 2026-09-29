import { useState } from 'react'
import { Modal } from '../ui/Modal'
import { Button } from '../ui/Button'

export function LostReasonModal({ onConfirm, onClose, isSubmitting }) {
  const [reason, setReason] = useState('')

  function handleSubmit(event) {
    event.preventDefault()
    onConfirm(reason)
  }

  return (
    <Modal title="Motivo da perda" onClose={onClose}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div className="flex flex-col gap-1">
          <label htmlFor="lost-reason" className="text-sm font-medium text-text">
            Motivo
          </label>
          <textarea
            id="lost-reason"
            rows={3}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            required
            className="rounded-lg border border-border px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={isSubmitting || !reason.trim()}>
            {isSubmitting ? 'Enviando…' : 'Confirmar'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
