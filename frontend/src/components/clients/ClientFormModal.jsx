import { useState } from 'react'
import { Modal } from '../ui/Modal'
import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { Button } from '../ui/Button'
import { AssigneeSelect } from '../shared/AssigneeSelect'
import { useAuth } from '../../hooks/useAuth'
import { useCreateClient } from '../../hooks/useCreateClient'
import { useUpdateClient } from '../../hooks/useUpdateClient'
import { STATUS_OPTIONS, TYPE_OPTIONS } from '../../utils/clientOptions'
import { getFieldError } from '../../utils/apiErrors'

export function ClientFormModal({ client, onClose }) {
  const isEditing = Boolean(client)
  const { role } = useAuth()
  const canAssign = role === 'admin' || role === 'manager'

  const [name, setName] = useState(client?.name ?? '')
  const [email, setEmail] = useState(client?.email ?? '')
  const [phone, setPhone] = useState(client?.phone ?? '')
  const [documentNumber, setDocumentNumber] = useState(client?.document ?? '')
  const [type, setType] = useState(client?.type ?? 'individual')
  const [status, setStatus] = useState(client?.status ?? 'active')
  const [notes, setNotes] = useState(client?.notes ?? '')
  const [userId, setUserId] = useState(client?.assigned_to?.id ?? '')

  const createClient = useCreateClient()
  const updateClient = useUpdateClient()
  const mutation = isEditing ? updateClient : createClient

  function handleSubmit(event) {
    event.preventDefault()

    const payload = {
      name,
      email: email || null,
      phone: phone || null,
      document: documentNumber || null,
      type,
      notes: notes || null,
    }

    if (canAssign) {
      payload.user_id = userId || null
    }

    if (isEditing) {
      payload.status = status
      updateClient.mutate(
        { id: client.id, payload },
        { onSuccess: onClose },
      )
    } else {
      createClient.mutate(payload, { onSuccess: onClose })
    }
  }

  return (
    <Modal title={isEditing ? 'Editar cliente' : 'Novo cliente'} onClose={onClose}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <Input
          id="client-name"
          label="Nome"
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={getFieldError(mutation.error, 'name')}
          required
        />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            id="client-email"
            label="E-mail"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={getFieldError(mutation.error, 'email')}
          />
          <Input
            id="client-phone"
            label="Telefone"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
            error={getFieldError(mutation.error, 'phone')}
          />
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Select
            id="client-type"
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
            id="client-document"
            label="Documento"
            placeholder="CPF ou CNPJ"
            value={documentNumber}
            onChange={(event) => setDocumentNumber(event.target.value)}
            error={getFieldError(mutation.error, 'document')}
          />
        </div>

        {isEditing && (
          <Select
            id="client-status"
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

        <AssigneeSelect
          id="client-assignee"
          value={userId}
          onChange={(event) => setUserId(event.target.value)}
          error={getFieldError(mutation.error, 'user_id')}
          canAssign={canAssign}
        />

        <div className="flex flex-col gap-1">
          <label htmlFor="client-notes" className="text-sm font-medium text-text">
            Observações
          </label>
          <textarea
            id="client-notes"
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
