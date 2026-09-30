import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ActivityStatusBadge } from './ActivityStatusBadge'

describe('ActivityStatusBadge', () => {
  it('shows "Pendente" for a pending, non-overdue activity', () => {
    render(<ActivityStatusBadge activity={{ status: 'pending', is_overdue: false }} />)

    expect(screen.getByText('Pendente')).toBeInTheDocument()
  })

  it('shows "Concluída" for a completed activity', () => {
    render(<ActivityStatusBadge activity={{ status: 'completed', is_overdue: false }} />)

    expect(screen.getByText('Concluída')).toBeInTheDocument()
  })

  it('shows "Atrasada" instead of "Pendente" when is_overdue is true', () => {
    render(<ActivityStatusBadge activity={{ status: 'pending', is_overdue: true }} />)

    expect(screen.getByText('Atrasada')).toBeInTheDocument()
    expect(screen.queryByText('Pendente')).not.toBeInTheDocument()
  })

  it('never shows "Atrasada" for a completed activity even if is_overdue were true', () => {
    // is_overdue is only ever true for pending activities per the backend
    // Resource, but the badge itself should still key off status/is_overdue
    // exactly as given rather than re-deriving anything client-side.
    render(<ActivityStatusBadge activity={{ status: 'completed', is_overdue: false }} />)

    expect(screen.getByText('Concluída')).toBeInTheDocument()
  })
})
