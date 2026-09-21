import { useAuth } from '../../hooks/useAuth'
import { useLogout } from '../../hooks/useLogout'
import { Button } from '../ui/Button'

export function Header({ onToggleSidebar }) {
  const { user } = useAuth()
  const logout = useLogout()

  return (
    <header className="flex h-16 items-center justify-between border-b border-border bg-surface px-4 sm:px-6">
      <button
        type="button"
        onClick={onToggleSidebar}
        aria-label="Abrir menu"
        className="rounded-md p-2 text-muted hover:bg-background hover:text-text md:hidden"
      >
        ☰
      </button>

      <div className="hidden flex-col md:flex">
        <span className="text-sm font-medium text-text">{user?.company?.name}</span>
      </div>

      <div className="flex items-center gap-3">
        <div className="hidden text-right sm:block">
          <p className="text-sm font-medium text-text">{user?.name}</p>
          <p className="text-xs text-muted">{user?.role}</p>
        </div>
        <Button
          variant="secondary"
          onClick={() => logout.mutate()}
          disabled={logout.isPending}
        >
          Sair
        </Button>
      </div>
    </header>
  )
}
