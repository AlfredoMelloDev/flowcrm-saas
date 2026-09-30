import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { UpcomingActivitiesList } from './UpcomingActivitiesList'

describe('UpcomingActivitiesList', () => {
  it('shows an empty message when there are no activities', () => {
    render(<UpcomingActivitiesList activities={[]} />)

    expect(screen.getByText('Nenhuma atividade próxima.')).toBeInTheDocument()
  })

  it('renders activity title, type, assignee and scheduled date/time', () => {
    render(
      <UpcomingActivitiesList
        activities={[
          {
            id: 'a1',
            title: 'Follow-up call',
            type: 'call',
            scheduled_at: '2026-10-05T14:30:00.000000Z',
            assigned_to: { id: 'u1', name: 'Bob' },
          },
        ]}
      />,
    )

    expect(screen.getByText('Follow-up call')).toBeInTheDocument()
    expect(screen.getByText('Ligação')).toBeInTheDocument()
    expect(screen.getByText('Bob')).toBeInTheDocument()
  })
})
