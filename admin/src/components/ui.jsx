import { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { hasPerm } from '../features/authSlice';

export const cls = (...a) => a.filter(Boolean).join(' ');

export function Button({ variant, size, loading, children, className, ...p }) {
  return (
    <button className={cls('btn', variant, size, className)} disabled={loading || p.disabled} {...p}>
      {loading ? '…' : children}
    </button>
  );
}

export const Card = ({ title, actions, flush, children, className }) => (
  <div className={cls('card', flush && 'flush', className)}>
    {(title || actions) && (
      <div className="row between" style={flush ? { padding: '14px 16px 0' } : { marginBottom: 12 }}>
        {title && <h2 style={{ margin: 0 }}>{title}</h2>}
        {actions && <div className="row">{actions}</div>}
      </div>
    )}
    {children}
  </div>
);

const TONES = {
  active: 'green', published: 'green', paid: 'green', live: 'red', evaluated: 'green', sent: 'green', done: 'green', converted: 'green', approved: 'green', processed: 'green',
  draft: 'grey', scheduled: 'orange', pending: 'orange', created: 'orange', contacted: 'orange', planned: 'orange', ready: 'purple', importing: 'purple', sending: 'purple',
  blocked: 'red', failed: 'red', expired: 'grey', archived: 'grey', cancelled: 'grey', refunded: 'purple', lost: 'grey', ended: 'grey', revoked: 'red', closed: 'grey', new: '',
};
export const Badge = ({ tone, children }) => <span className={cls('badge', tone ?? TONES[String(children).toLowerCase()])}>{children}</span>;

export const Stat = ({ label, value, hint, icon }) => (
  <div className="card stat">
    <div className="row between"><span className="l">{label}</span>{icon && <span>{icon}</span>}</div>
    <div className="v">{value ?? '—'}</div>
    {hint && <div className="small muted">{hint}</div>}
  </div>
);

export const Toggle = ({ on, onChange, disabled, label }) => (
  <button type="button" role="switch" aria-checked={!!on} aria-label={label} disabled={disabled} className={cls('toggle', on && 'on')} onClick={() => onChange(!on)} />
);

export function Tabs({ tabs, value, onChange }) {
  return (
    <div className="tabs" role="tablist">
      {tabs.filter(Boolean).map((t) => (
        <button key={t.key} role="tab" className={value === t.key ? 'on' : ''} onClick={() => onChange(t.key)}>{t.label}</button>
      ))}
    </div>
  );
}

export function Modal({ title, onClose, children, footer, wide }) {
  useEffect(() => {
    const k = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', k);
    return () => window.removeEventListener('keydown', k);
  }, [onClose]);
  return (
    <div className="modalbg" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className={cls('modal', wide && 'wide')} role="dialog" aria-modal="true" aria-label={title}>
        <div className="mh"><h2 style={{ margin: 0 }} className="grow">{title}</h2><button className="btn ghost sm" onClick={onClose} aria-label="Close">✕</button></div>
        <div className="mb">{children}</div>
        {footer && <div className="mf">{footer}</div>}
      </div>
    </div>
  );
}

export const Spinner = () => <div className="spinner" aria-label="Loading" />;
export const Empty = ({ icon = '🗂️', title = 'Nothing here yet', children }) => (
  <div className="empty"><div className="e">{icon}</div><div className="b" style={{ margin: '8px 0 4px' }}>{title}</div><div>{children}</div></div>
);

export const PageHead = ({ title, sub, children }) => (
  <div className="phead">
    <div className="grow"><h1>{title}</h1>{sub && <div className="sub">{sub}</div>}</div>
    {children}
  </div>
);

export function useMe() {
  return useSelector((s) => s.auth.user);
}
export function useCan() {
  const me = useMe();
  return (perm) => hasPerm(me, perm);
}
export const Can = ({ perm, children, fallback = null }) => (useCan()(perm) ? children : fallback);

/** Debounced value for search boxes */
export function useDebounced(value, ms = 350) {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setV(value), ms);
    return () => clearTimeout(t);
  }, [value, ms]);
  return v;
}

export const fmtDate = (s, withTime) => (s ? new Date(s).toLocaleString('en-IN', withTime
  ? { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }
  : { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
export const ago = (s) => {
  if (!s) return 'never';
  const d = (Date.now() - new Date(s).getTime()) / 1000;
  if (d < 60) return 'just now';
  if (d < 3600) return `${Math.floor(d / 60)} min ago`;
  if (d < 86400) return `${Math.floor(d / 3600)} h ago`;
  return `${Math.floor(d / 86400)} d ago`;
};
export const initials = (n = '') => n.split(/\s+/).slice(0, 2).map((x) => x[0]).join('').toUpperCase() || 'U';
export const Avatar = ({ name, emoji }) => <span className="avatar">{emoji || initials(name)}</span>;

export function Confirm({ text, onYes, onClose, danger = true, yes = 'Yes, continue' }) {
  return (
    <Modal title="Are you sure?" onClose={onClose} footer={<><Button onClick={onClose}>Cancel</Button><Button variant={danger ? 'primary danger' : 'primary'} onClick={() => { onYes(); onClose(); }}>{yes}</Button></>}>
      <p style={{ margin: 0 }}>{text}</p>
    </Modal>
  );
}
