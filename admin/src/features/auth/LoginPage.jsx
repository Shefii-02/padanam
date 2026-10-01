import { useState } from 'react';
import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { useLoginMutation } from './api';
import { loggedIn } from '../authSlice';
import { Button } from '../../components/ui';

export default function LoginPage() {
  const [login, { isLoading, error }] = useLoginMutation();
  const [form, setForm] = useState({ email: '', password: '' });
  const dispatch = useDispatch();
  const nav = useNavigate();

  const submit = async (e) => {
    e.preventDefault();
    try {
      const res = await login(form).unwrap();
      dispatch(loggedIn({ token: res.token, user: res.user }));
      nav('/', { replace: true });
    } catch { /* toast shows the message */ }
  };

  return (
    <div style={{ minHeight: '100%', display: 'grid', placeItems: 'center', padding: 16 }}>
      <form className="card col" style={{ width: 'min(400px,100%)', padding: 28 }} onSubmit={submit}>
        <div className="logo" style={{ padding: 0 }}><b>പ</b><span>Padanam Admin</span></div>
        <p className="muted" style={{ margin: 0 }}>For admins, staff and teachers.</p>
        <div className="field"><label htmlFor="email">Email</label><input id="email" className="input" type="email" autoComplete="username" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
        <div className="field"><label htmlFor="pw">Password</label><input id="pw" className="input" type="password" autoComplete="current-password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>
        {error && <div className="small" style={{ color: 'var(--red)' }}>{error.message}</div>}
        <Button variant="primary" type="submit" loading={isLoading}>Log in</Button>
      </form>
    </div>
  );
}
