import { useState } from 'react'
import { Modal } from '../ui/Modal'
import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { Button } from '../ui/Button'
import { useAuth } from '../../hooks/useAuth'
import { useAssignableUsers } from '../../hooks/useAssignableUsers'
import { useCreateLead } from '../../hooks/useCreateLead'
import { useUpdateLead } from '../../hooks/useUpdateLead'
import { STATUS_OPTIONS, SOURCE_OPTIONS } from '../../utils/leadOptions'
import { getFieldError } from '../../utils/apiErrors'

export function LeadFormModal({ lead, onClose }) {
  const isEditing = Boolean(lead)
  const { role } = useAuth()
  const canAssign = role === 'admin' || role === 'manager'

  const [name, setName] = useState(lead?.name ?? '')
  const [email, setEmail] = useState(lead?.email ?? '')
  const [phone, setPhone] = useState(lead?.phone ?? '')
  const [source, setSource] = useState(lead?.source ?? '')
  const [status, setStatus] = useState(lead?.status ?? 'new')
  const [estimatedValue, setEstimatedValue] = useState(lead?.estimated_value ?? '')
  const [notes, setNotes] = useState(lead?.notes ?? '')
  const [userId, setUserId] = useState(lead?.assigned_to?.id ?? '')

  // Only fetched when it will actually be shown — a Seller never needs this list.
  const assignableUsers = useAssignableUsers(canAssign)

  const createLead = useCreateLead()
  const updateLead = useUpdateLead()
  const mutation = isEditing ? updateLead : createLead

  function handleSubmit(event) {
    event.preventDefault()

    const payload = {
      name,
      email: email || null,
      phone: phone || null,
      source: source || null,
      estimated_value: estimatedValue === '' ? null : estimatedValue,
      notes: notes || null,
    }

    if (canAssign) {
      payload.user_id = userId || null
    }

    if (isEditing) {
      payload.status = status
      updateLead.mutate(
        { id: lead.id, payload },
        { onSuccess: onClose },
      )
    } else {
      createLead.mutate(payload, { onSuccess: onClose })
    }
  }

  return (
    <Modal title={isEditing ? 'Editar lead' : 'Novo lead'} onClose={onClose}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          id="lead-name"
          label="Nome"
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={getFieldError(mutation.error, 'name')}
          required
        />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            id="lead-email"
            label="E-mail"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={getFieldError(mutation.error, 'email')}
          />
          <Input
            id="lead-phone"
            label="Telefone"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            error={getFieldError(mutation.error, 'phone')}
          />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Select
            id="lead-source"
            label="Origem"
            value={source}
            onChange={(event) => setSource(event.target.value)}
            error={getFieldError(mutation.error, 'source')}
          >
            <option value="">Não informada</option>
            {SOURCE_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>

          <Input
            id="lead-estimated-value"
            label="Valor estimado"
            type="number"
            step="0.01"
            min="0"
            value={estimatedValue}
            onChange={(event) => setEstimatedValue(event.target.value)}
            error={getFieldError(mutation.error, 'estimated_value')}
          />
        </div>

        {isEditing && (
          <Select
            id="lead-status"
            label="Status"
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            error={getFieldError(mutation.error, 'status')}
          >
            {STATUS_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </Select>
        )}

        {canAssign && (
          <Select
            id="lead-assignee"
            label="Responsável"
            value={userId}
            onChange={(event) => setUserId(event.target.value)}
            error={getFieldError(mutation.error, 'user_id')}
            disabled={assignableUsers.isLoading}
          >
            <option value="">Sem responsável</option>
            {assignableUsers.data?.map((assignableUser) => (
              <option key={assignableUser.id} value={assignableUser.id}>
                {assignableUser.name}
              </option>
            ))}
          </Select>
        )}

        <div className="flex flex-col gap-1">
          <label htmlFor="lead-notes" className="text-sm font-medium text-text">
            Observações
          </label>
          <textarea
            id="lead-notes"
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
