import { createSlice } from '@reduxjs/toolkit';

let id = 0;
const slice = createSlice({
  name: 'ui',
  initialState: { toasts: [], sideOpen: false, theme: localStorage.getItem('pdn_theme') || 'light' },
  reducers: {
    toastAdded: { reducer(s, { payload }) { s.toasts.push(payload); }, prepare: (text, kind = 'ok') => ({ payload: { id: ++id, text, kind } }) },
    toastRemoved(s, { payload }) { s.toasts = s.toasts.filter((t) => t.id !== payload); },
    toggleSide(s, { payload }) { s.sideOpen = payload ?? !s.sideOpen; },
    toggleTheme(s) { s.theme = s.theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('pdn_theme', s.theme); },
  },
});

export const { toastAdded, toastRemoved, toggleSide, toggleTheme } = slice.actions;
export default slice.reducer;

export const toast = (text, kind) => (dispatch) => {
  const action = dispatch(toastAdded(text, kind));
  setTimeout(() => dispatch(toastRemoved(action.payload.id)), kind === 'error' ? 6000 : 3500);
};
