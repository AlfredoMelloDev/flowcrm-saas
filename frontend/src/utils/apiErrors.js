// Laravel's validation error shape: { message, errors: { field: ["msg", ...] } }
export function getFieldErrors(error) {
  return error?.response?.data?.errors ?? {}
}

export function getFieldError(error, field) {
  return getFieldErrors(error)[field]?.[0]
}

export function getErrorMessage(error, fallback = 'Algo deu errado. Tente novamente.') {
  return error?.response?.data?.message ?? fallback
}
