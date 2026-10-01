import { formatCurrency } from '../../utils/formatters'

export function PerformanceByUserTable({ performanceByUser }) {
  return (
    <div className="rounded-xl border border-border bg-surface p-4">
      <h2 className="mb-3 text-sm font-semibold text-text">Desempenho por responsável</h2>
      {performanceByUser.length === 0 ? (
        <p className="text-sm text-muted">Nenhum dado de desempenho no período.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border text-xs uppercase text-muted">
              <tr>
                <th className="py-2 pr-4 font-medium">Responsável</th>
                <th className="py-2 pr-4 font-medium">Leads convertidos</th>
                <th className="py-2 pr-4 font-medium">Oportunidades ganhas</th>
                <th className="py-2 pr-4 font-medium">Valor ganho</th>
                <th className="py-2 pr-4 font-medium">Atividades concluídas</th>
              </tr>
            </thead>
            <tbody>
              {performanceByUser.map((row) => (
                <tr key={row.user_id} className="border-b border-border last:border-0">
                  <td className="py-2 pr-4 font-medium text-text">{row.name}</td>
                  <td className="py-2 pr-4 text-muted">{row.leads_converted}</td>
                  <td className="py-2 pr-4 text-muted">{row.opportunities_won}</td>
                  <td className="py-2 pr-4 text-muted">{formatCurrency(row.value_won)}</td>
                  <td className="py-2 pr-4 text-muted">{row.activities_completed}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
