const VARIANTS = {
  primary:
    'bg-primary text-white hover:bg-primary-dark focus-visible:outline-primary',
  secondary:
    'bg-surface text-text border border-border hover:bg-background focus-visible:outline-primary',
  danger:
    'bg-surface text-danger border border-border hover:bg-danger/10 focus-visible:outline-danger',
  ghost: 'text-muted hover:text-text hover:bg-background',
}

export function Button({
  variant = 'primary',
  className = '',
  disabled = false,
  type = 'button',
  children,
  ...props
}) {
  return (
    <button
      type={type}
      disabled={disabled}
      className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${VARIANTS[variant]} ${className}`}
      {...props}
    >
      {children}
    </button>
  )
}
