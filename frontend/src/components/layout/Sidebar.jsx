import { NavLink } from 'react-router-dom'

const NAV_ITEMS = [
  { to: '/dashboard', label: 'Dashboard' },
  { to: '/leads', label: 'Leads' },
  { to: '/clients', label: 'Clientes' },
  { to: '/opportunities', label: 'Oportunidades' },
  { to: '/activities', label: 'Atividades' },
]

function NavItems({ onNavigate }) {
  return (
    <nav className="flex flex-col gap-1 px-3">
      {NAV_ITEMS.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          onClick={onNavigate}
          className={({ isActive }) =>
            `rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
              isActive
                ? 'bg-primary/10 text-primary'
                : 'text-muted hover:bg-background hover:text-text'
            }`
          }
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
  )
}

export function Sidebar({ mobileOpen = false, onClose }) {
  return (
    <>
      {/* Desktop: always visible, static column. */}
      <aside className="hidden w-56 shrink-0 flex-col border-r border-border bg-surface md:flex">
        <div className="flex h-16 items-center px-6">
          <span className="text-lg font-semibold text-text">FlowCRM</span>
        </div>
        <NavItems />
      </aside>

      {/* Mobile: off-canvas drawer, only mounted while open. */}
      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            aria-label="Fechar menu"
            onClick={onClose}
            className="absolute inset-0 bg-text/40"
          />
          <aside className="relative z-10 flex h-full w-64 flex-col bg-surface shadow-lg">
            <div className="flex h-16 items-center px-6">
              <span className="text-lg font-semibold text-text">FlowCRM</span>
            </div>
            <NavItems onNavigate={onClose} />
          </aside>
        </div>
      )}
    </>
  )
}
