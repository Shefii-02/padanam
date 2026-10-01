import { createSlice } from '@reduxjs/toolkit';

const saved = () => {
  try { return JSON.parse(localStorage.getItem('pdn_user') || 'null'); } catch { return null; }
};

const slice = createSlice({
  name: 'auth',
  initialState: { token: localStorage.getItem('pdn_token'), user: saved() },
  reducers: {
    loggedIn(state, { payload }) {
      state.token = payload.token;
      state.user = payload.user;
      localStorage.setItem('pdn_token', payload.token);
      localStorage.setItem('pdn_user', JSON.stringify(payload.user));
    },
    setUser(state, { payload }) {
      state.user = payload;
      localStorage.setItem('pdn_user', JSON.stringify(payload));
    },
    logout(state) {
      state.token = null;
      state.user = null;
      localStorage.removeItem('pdn_token');
      localStorage.removeItem('pdn_user');
    },
  },
});

export const { loggedIn, setUser, logout } = slice.actions;
export default slice.reducer;

/** Permission check used everywhere (menus, buttons, routes). */
export const hasPerm = (user, perm) => {
  if (!perm) return true;
  const p = user?.permissions || [];
  if (p.includes('*')) return true;
  return (Array.isArray(perm) ? perm : [perm]).some((x) => p.includes(x));
};
