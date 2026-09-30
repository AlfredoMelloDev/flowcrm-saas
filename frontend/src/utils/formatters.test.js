import { describe, expect, it } from 'vitest'
import { formatDate, formatDateOnly } from './formatters'

describe('formatDateOnly', () => {
  it('formats a full ISO midnight-UTC datetime without shifting to the previous day', () => {
    // This is exactly the shape the API sends for a "date" cast
    // (expected_close_date): a real bug showed this rendering as 02/10/2026
    // for any viewer in a negative UTC offset before this fix.
    expect(formatDateOnly('2026-10-03T00:00:00.000000Z')).toBe('03/10/2026')
  })

  it('formats a bare YYYY-MM-DD string the same way', () => {
    expect(formatDateOnly('2026-10-03')).toBe('03/10/2026')
  })

  it('is not affected by a time-of-day component other than midnight', () => {
    expect(formatDateOnly('2026-10-03T23:00:00.000000Z')).toBe('03/10/2026')
  })

  it('returns a placeholder for an empty value', () => {
    expect(formatDateOnly(null)).toBe('—')
    expect(formatDateOnly(undefined)).toBe('—')
    expect(formatDateOnly('')).toBe('—')
  })
})

describe('formatDate (regression guard — real timestamps keep converting through local time)', () => {
  it('matches a plain (non-pinned) Intl formatting of the same timestamp', () => {
    // Deliberately not hardcoding a day/month here: formatDate must still
    // read through whatever local timezone the machine running the test is
    // in (unlike formatDateOnly, which is now pinned to UTC on purpose) —
    // comparing against an unpinned Intl call keeps this assertion valid
    // regardless of the runner's own timezone.
    const timestamp = '2026-01-15T23:30:00.000000Z'
    const expected = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(new Date(timestamp))

    expect(formatDate(timestamp)).toBe(expected)
  })

  it('returns a placeholder for an empty value', () => {
    expect(formatDate(null)).toBe('—')
  })
})
