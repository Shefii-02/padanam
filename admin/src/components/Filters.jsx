import { useEffect, useState } from 'react';
import { useDebounced } from './ui';

/** Search + select filters in one row. filters: [{ name, label, options:[{value,label}] }] */
export default function Filters({ value, onChange, filters = [], search = true, placeholder = 'Search…', children }) {
  const [q, setQ] = useState(value.search || '');
  const dq = useDebounced(q);
  useEffect(() => {
    if ((value.search || '') !== dq) onChange({ ...value, search: dq, page: 1 });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dq]);
  return (
    <div className="row wrap" style={{ marginBottom: 12 }}>
      {search && <input className="input" style={{ maxWidth: 280 }} placeholder={placeholder} value={q} onChange={(e) => setQ(e.target.value)} aria-label="Search" />}
      {filters.map((f) => (
        <select key={f.name} className="input" style={{ width: 'auto' }} value={value[f.name] ?? ''} aria-label={f.label}
          onChange={(e) => onChange({ ...value, [f.name]: e.target.value, page: 1 })}>
          <option value="">{f.label}: all</option>
          {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      ))}
      {children}
    </div>
  );
}
