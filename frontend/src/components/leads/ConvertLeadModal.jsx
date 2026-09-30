import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Modal } from '../ui/Modal'
import { Input } from '../ui/Input'
import { Select } from '../ui/Select'
import { Button } from '../ui/Button'
import { useConvertLead } from '../../hooks/useConvertLead'
import { getFieldError, getErrorMessage } from '../../utils/apiErrors'

export function ConvertLeadModal({ lead, onClose }) {
  const navigate = useNavigate()

  const [clientDocument, setClientDocument] = useState('')
  const [clientType, setClientType] = useState('individual')
  const [opportunityTitle, setOpportunityTitle] = useState(lead.name)
  const [opportunityValue, setOpportunityValue] = useState(lead.estimated_value ?? '')
  const [expectedCloseDate, setExpectedCloseDate] = useState('')
  const [notes, setNotes] = useState('')

  const convertLead = useConvertLead()

  function handleSubmit(event) {
    event.preventDefault()

    convertLead.mutate(
      {
        id: lead.id,
        payload: {
          client_document: clientDocument || null,
          client_type: clientType,
          opportunity_title: opportunityTitle || null,
          opportunity_value: opportunityValue === '' ? null : opportunityValue,
          expected_close_date: expectedCloseDate || null,
          notes: notes || null,
        },
      },
      {
        onSuccess: () => {
          onClose()
          navigate('/opportunities')
        },
      },
    )
  }

  const generalError =
    convertLead.isError && !getFieldError(convertLead.error, 'client_document')
      ? convertLead.error.response?.status === 409
        ? 'Este lead já foi convertido.'
        : getErrorMessage(convertLead.error)
      : null

  return (
    <Modal title="Converter lead" onClose={onClose}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div className="rounded-lg border border-border bg-background p-3 text-sm">
          <p className="font-medium text-text">{lead.name}</p>
          <p className="text-muted">{lead.email ?? '—'}</p>
          <p className="text-muted">{lead.phone ?? '—'}</p>
        </div>

        {generalError && <p className="text-sm text-danger">{generalError}</p>}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            id="convert-client-document"
            label="Documento do cliente"
            placeholder="Opcional"
            value={clientDocument}
            onChange={(event) => setClientDocument(event.target.value)}
            error={getFieldError(convertLead.error, 'client_document')}
          />
          <Select
            id="convert-client-type"
            label="Tipo de cliente"
            value={clientType}
            onChange={(event) => setClientType(event.target.value)}
          >
            <option value="individual">Pessoa física</option>
            <option value="company">Pessoa jurídica</option>
          </Select>
        </div>

        <Input
          id="convert-opportunity-title"
          label="Título da oportunidade"
          value={opportunityTitle}
          onChange={(event) => setOpportunityTitle(event.target.value)}
          error={getFieldError(convertLead.error, 'opportunity_title')}
          required
        />

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            id="convert-opportunity-value"
            label="Valor da oportunidade"
            inputMode="decimal"
            placeholder="0.00"
            value={opportunityValue}
            onChange={(event) => setOpportunityValue(event.target.value)}
            error={getFieldError(convertLead.error, 'opportunity_value')}
          />
          <Input
            id="convert-expected-close-date"
            label="Previsão de fechamento"
            type="date"
            value={expectedCloseDate}
            onChange={(event) => setExpectedCloseDate(event.target.value)}
            error={getFieldError(convertLead.error, 'expected_close_date')}
          />
        </div>

        <div className="flex flex-col gap-1">
          <label htmlFor="convert-notes" className="text-sm font-medium text-text">
            Observações da oportunidade
          </label>
          <textarea
            id="convert-notes"
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
          <Button type="submit" disabled={convertLead.isPending}>
            {convertLead.isPending ? 'Convertendo…' : 'Confirmar conversão'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
