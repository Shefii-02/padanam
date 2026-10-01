import { Spinner, Empty } from './ui';

/**
 * columns: [{ key, label, render:(row)=>node, className }]
 * meta.pagination from Laravel → pager
 */
export default function DataTable({ columns, rows = [], loading, onRow, meta, page, onPage, empty }) {
  const p = meta?.pagination;
  return (
    <div>
      <div className="tablewrap">
        <table className="t">
          <thead><tr>{columns.map((c) => <th key={c.key} className={c.className}>{c.label}</th>)}</tr></thead>
          <tbody>
            {!loading && rows.map((r, i) => (
              <tr key={r.id ?? r.attempt ?? i} className={onRow ? 'click' : ''} onClick={onRow ? () => onRow(r) : undefined}>
                {columns.map((c) => <td key={c.key} className={c.className}>{c.render ? c.render(r) : r[c.key] ?? '—'}</td>)}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {loading && <Spinner />}
      {!loading && !rows.length && (empty || <Empty />)}
      {p && p.last_page > 1 && (
        <div className="pager">
          <span>Page {p.page} of {p.last_page} · {p.total} total</span>
          <span className="row">
            <button className="btn sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>← Prev</button>
            <button className="btn sm" disabled={page >= p.last_page} onClick={() => onPage(page + 1)}>Next →</button>
          </span>
        </div>
      )}
    </div>
  );
}
