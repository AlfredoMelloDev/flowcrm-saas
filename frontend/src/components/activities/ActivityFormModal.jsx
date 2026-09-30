import { useState } from 'react'
import { Modal } from '../ui/Modal'
import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { Button } from '../ui/Button'
import { AssigneeSelect } from '../shared/AssigneeSelect'
import { LeadSelect } from '../shared/LeadSelect'
import { ClientSelect } from '../shared/ClientSelect'
import { OpportunitySelect } from '../shared/OpportunitySelect'
import { useAuth } from '../../hooks/useAuth'
import { useCreateActivity } from '../../hooks/useCreateActivity'
import { useUpdateActivity } from '../../hooks/useUpdateActivity'
import { TYPE_OPTIONS, RELATION_TYPE_OPTIONS } from '../../utils/activityOptions'
import { getFieldError } from '../../utils/apiErrors'

// <input type="datetime-local"> takes/returns a "YYYY-MM-DDTHH:mm" string
// with NO timezone info, which both the browser and the Date constructor
// treat as the viewer's own local time (unlike a bare "YYYY-MM-DD" date,
// which is treated as UTC — see formatDateOnly). So converting API<->input
// here never needs to pin or fight a timezone, just read/write local
// components consistently in both directions.
function toDateTimeLocalValue(isoString) {
  if (!isoString) {
    return ''
  }
  const date = new Date(isoString)
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function relationTypeOf(activity) {
  if (activity?.lead) return 'lead'
  if (activity?.client) return 'client'
  if (activity?.opportunity) return 'opportunity'
  return ''
}

export function ActivityFormModal({ activity, onClose }) {
  const isEditing = Boolean(activity)
  const { role, user } = useAuth()
  const canAssign = role === 'admin' || role === 'manager'

  const [title, setTitle] = useState(activity?.title ?? '')
  const [type, setType] = useState(activity?.type ?? 'task')
  const [description, setDescription] = useState(activity?.description ?? '')
  const [scheduledAt, setScheduledAt] = useState(toDateTimeLocalValue(activity?.scheduled_at))
  const [relationType, setRelationType] = useState(relationTypeOf(activity))
  const [relatedId, setRelatedId] = useState(
    activity?.lead?.id ?? activity?.client?.id ?? activity?.opportunity?.id ?? '',
  )
  const [userId, setUserId] = useState(activity?.assigned_to?.id ?? user?.id ?? '')

  const createActivity = useCreateActivity()
  const updateActivity = useUpdateActivity()
  const mutation = isEditing ? updateActivity : createActivity

  function handleRelationTypeChange(event) {
    setRelationType(event.target.value)
    setRelatedId('')
  }

  function handleSubmit(event) {
    event.preventDefault()

    const payload = {
      title,
      type,
      description: description || null,
      scheduled_at: scheduledAt ? new Date(scheduledAt).toISOString() : null,
      lead_id: relationType === 'lead' ? relatedId || null : null,
      client_id: relationType === 'client' ? relatedId || null : null,
      opportunity_id: relationType === 'opportunity' ? relatedId || null : null,
    }

    if (canAssign) {
      payload.user_id = userId
    }

    if (isEditing) {
      updateActivity.mutate({ id: activity.id, payload }, { onSuccess: onClose })
    } else {
      createActivity.mutate(payload, { onSuccess: onClose })
    }
  }

  return (
    <Modal title={isEditing ? 'Editar atividade' : 'Nova atividade'} onClose={onClose}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          id="activity-title"
          label="Título"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          error={getFieldError(mutation.error, 'title')}
          required
        />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Select
            id="activity-type"
            label="Tipo"
            value={type}
            onChange={(event) => setType(event.target.value)}
            error={getFieldError(mutation.error, 'type')}
          >
            {TYPE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>

          <Input
            id="activity-scheduled-at"
            label="Agendado para"
            type="datetime-local"
            value={scheduledAt}
            onChange={(event) => setScheduledAt(event.target.value)}
            error={getFieldError(mutation.error, 'scheduled_at')}
            required
          />
        </div>

        <Select
          id="activity-relation-type"
          label="Relacionado a"
          value={relationType}
          onChange={handleRelationTypeChange}
        >
          {RELATION_TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>

        {relationType === 'lead' && (
          <LeadSelect
            id="activity-lead"
            value={relatedId}
            onChange={(event) => setRelatedId(event.target.value)}
            error={getFieldError(mutation.error, 'lead_id')}
            currentLead={activity?.lead}
          />
        )}
        {relationType === 'client' && (
          <ClientSelect
            id="activity-client"
            value={relatedId}
            onChange={(event) => setRelatedId(event.target.value)}
            error={getFieldError(mutation.error, 'client_id')}
            currentClient={activity?.client}
          />
        )}
        {relationType === 'opportunity' && (
          <OpportunitySelect
            id="activity-opportunity"
            value={relatedId}
            onChange={(event) => setRelatedId(event.target.value)}
            error={getFieldError(mutation.error, 'opportunity_id')}
            currentOpportunity={activity?.opportunity}
          />
        )}

        <AssigneeSelect
          id="activity-assignee"
          value={userId}
          onChange={(event) => setUserId(event.target.value)}
          error={getFieldError(mutation.error, 'user_id')}
          canAssign={canAssign}
          required
        />

        <div className="flex flex-col gap-1">
          <label htmlFor="activity-description" className="text-sm font-medium text-text">
            Descrição
          </label>
          <textarea
            id="activity-description"
            rows={3}
            value={description}
            onChange={(event) => setDescription(event.target.value)}
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
