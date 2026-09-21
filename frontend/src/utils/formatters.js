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

export function formatDate(value) {
  if (!value) {
    return '—'
  }
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(new Date(value))
}
