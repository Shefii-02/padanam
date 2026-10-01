import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { useEffect } from 'react';
import { MENU } from '../app/menu';
import { hasPerm, logout } from '../features/authSlice';
import { toggleSide, toggleTheme, toastRemoved } from '../features/uiSlice';
import { api } from '../app/api';
import { Avatar, Badge } from './ui';

export default function Layout() {
  const me = useSelector((s) => s.auth.user);
  const { sideOpen, toasts, theme } = useSelector((s) => s.ui);
  const d = useDispatch();
  const loc = useLocation();
  useEffect(() => { d(toggleSide(false)); }, [loc.pathname, d]);

  const out = () => { d(logout()); d(api.util.resetApiState()); };

  return (
    <div className="app">
      <aside className={`side ${sideOpen ? 'open' : ''}`} aria-label="Main menu">
        <div className="logo"><b>പ</b><span>Padanam</span></div>
        <nav className="nav">
          {MENU.map((g) => {
            const items = g.items.filter((i) => hasPerm(me, i.perm));
            if (!items.length) return null;
            return (
              <div key={g.group}>
                <div className="navg">{g.group}</div>
                {items.map((i) => <NavLink key={i.to} to={i.to} end={i.end}><i>{i.icon}</i>{i.label}</NavLink>)}
              </div>
            );
          })}
        </nav>
      </aside>
      <div className={`scrim ${sideOpen ? 'open' : ''}`} onClick={() => d(toggleSide(false))} />
      <div className="main">
        <header className="top">
          <button className="btn ghost menu" onClick={() => d(toggleSide())} aria-label="Open menu">☰</button>
          <div className="grow" />
          <button className="btn ghost" onClick={() => d(toggleTheme())} aria-label="Toggle theme">{theme === 'dark' ? '☀️' : '🌙'}</button>
          <div className="row">
            <Avatar name={me?.name} />
            <div className="small" style={{ lineHeight: 1.2 }}>
              <div className="b">{me?.name}</div>
              <Badge>{me?.role?.replace('_', ' ')}</Badge>
            </div>
          </div>
          <button className="btn sm" onClick={out}>Log out</button>
        </header>
        <main className="page"><Outlet /></main>
      </div>
      <div className="toasts" aria-live="polite">
        {toasts.map((t) => <div key={t.id} className={`toast ${t.kind === 'error' ? 'error' : ''}`} onClick={() => d(toastRemoved(t.id))}>{t.text}</div>)}
      </div>
    </div>
  );
}
