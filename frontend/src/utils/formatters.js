const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

export function formatCurrency(value) {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  return currencyFormatter.format(Number(value))
}

// For real timestamps (created_at, updated_at, converted_at...) — a moment
// in time, correctly shown in the viewer's own local timezone. Don't use
// this for a calendar-only value (see formatDateOnly below): converting a
// date-only value through local time can shift it to the previous day for
// any viewer west of UTC.
export function formatDate(value) {
  if (!value) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(new Date(value))
}

// For calendar-only values (expected_close_date...) — there is no "moment"
// to convert to the viewer's timezone, only a date. The API sends these as
// midnight UTC (either "2026-10-03" or "2026-10-03T00:00:00.000000Z" — both
// parse as UTC per the ISO 8601 date-only rule), so formatting is pinned to
// "UTC" to read back the same calendar date everywhere, instead of letting
// Intl.DateTimeFormat reinterpret that UTC midnight in the viewer's local
// offset (which rolls it back a day for anyone west of UTC, e.g. Brazil).
export function formatDateOnly(value) {
  if (!value) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeZone: 'UTC' }).format(new Date(value))
}
