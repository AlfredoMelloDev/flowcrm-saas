import { useState } from 'react'
import { Modal } from '../ui/Modal'
import { Input } from '../ui/Input'
import { Button } from '../ui/Button'
import { ClientSelect } from '../shared/ClientSelect'
import { AssigneeSelect } from '../shared/AssigneeSelect'
import { useAuth } from '../../hooks/useAuth'
import { useCreateOpportunity } from '../../hooks/useCreateOpportunity'
import { useUpdateOpportunity } from '../../hooks/useUpdateOpportunity'
import { getFieldError } from '../../utils/apiErrors'

// Stage is deliberately never edited here — a new Opportunity always starts
// at "new" (the store endpoint doesn't even accept "stage"), and changing
// stage on an existing one happens only through the pipeline card's own
// <Select>, which is also what derives closed_at/lost_reason server-side.
export function OpportunityFormModal({ opportunity, onClose }) {
  const isEditing = Boolean(opportunity)
  const { role } = useAuth()
  const canAssign = role === 'admin' || role === 'manager'

  const [title, setTitle] = useState(opportunity?.title ?? '')
  const [clientId, setClientId] = useState(opportunity?.client?.id ?? '')
  const [value, setValue] = useState(opportunity?.value ?? '')
  // The API serializes "expected_close_date" as a full ISO datetime
  // ("2026-12-25T00:00:00.000000Z"), but <input type="date"> only accepts
  // "YYYY-MM-DD" and silently renders blank for anything else — slicing
  // keeps the date input showing (and re-submitting) the actual value.
  const [expectedCloseDate, setExpectedCloseDate] = useState(
    opportunity?.expected_close_date ? opportunity.expected_close_date.slice(0, 10) : '',
  )
  const [notes, setNotes] = useState(opportunity?.notes ?? '')
  const [userId, setUserId] = useState(opportunity?.assigned_to?.id ?? '')

  const createOpportunity = useCreateOpportunity()
  const updateOpportunity = useUpdateOpportunity()
  const mutation = isEditing ? updateOpportunity : createOpportunity

  function handleSubmit(event) {
    event.preventDefault()

    const payload = {
      title,
      client_id: clientId,
      value: value || null,
      expected_close_date: expectedCloseDate || null,
      notes: notes || null,
    }

    if (canAssign) {
      payload.user_id = userId || null
    }

    if (isEditing) {
      updateOpportunity.mutate(
        { id: opportunity.id, payload },
        { onSuccess: onClose },
      )
    } else {
      createOpportunity.mutate(payload, { onSuccess: onClose })
    }
  }

  return (
    <Modal title={isEditing ? 'Editar oportunidade' : 'Nova oportunidade'} onClose={onClose}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          id="opportunity-title"
          label="Título"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          error={getFieldError(mutation.error, 'title')}
          required
        />

        <ClientSelect
          id="opportunity-client"
          value={clientId}
          onChange={(event) => setClientId(event.target.value)}
          error={getFieldError(mutation.error, 'client_id')}
          currentClient={opportunity?.client}
          required
        />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            id="opportunity-value"
            label="Valor"
            inputMode="decimal"
            placeholder="0.00"
            value={value}
            onChange={(event) => setValue(event.target.value)}
            error={getFieldError(mutation.error, 'value')}
          />
          <Input
            id="opportunity-expected-close-date"
            label="Previsão de fechamento"
            type="date"
            value={expectedCloseDate}
            onChange={(event) => setExpectedCloseDate(event.target.value)}
            error={getFieldError(mutation.error, 'expected_close_date')}
          />
        </div>

        <AssigneeSelect
          id="opportunity-assignee"
          value={userId}
          onChange={(event) => setUserId(event.target.value)}
          error={getFieldError(mutation.error, 'user_id')}
          canAssign={canAssign}
        />

        <div className="flex flex-col gap-1">
          <label htmlFor="opportunity-notes" className="text-sm font-medium text-text">
            Observações
          </label>
          <textarea
            id="opportunity-notes"
            rows={3}
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            className="rounded-lg border border-border px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-primary/40"
          />
        </div>

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Salvando…' : 'Salvar'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
