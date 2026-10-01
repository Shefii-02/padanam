import { configureStore, createListenerMiddleware, isFulfilled, isRejectedWithValue } from '@reduxjs/toolkit';
import { api } from './api';
import auth from '../features/authSlice';
import ui, { toast } from '../features/uiSlice';

/** Every mutation shows the server's message as a toast (success or error) – no per-page code needed. */
const listener = createListenerMiddleware();
listener.startListening({
  predicate: (action) => (isFulfilled(action) || isRejectedWithValue(action)) && action.meta?.arg?.type === 'mutation',
  effect: (action, { dispatch }) => {
    if (action.meta.arg.originalArgs?.silent) return;
    if (isFulfilled(action)) {
      const msg = action.meta.baseQueryMeta?.message;
      if (msg && msg !== 'OK') dispatch(toast(msg));
    } else {
      dispatch(toast(action.payload?.message || 'Something went wrong', 'error'));
    }
  },
});

export const store = configureStore({
  reducer: { [api.reducerPath]: api.reducer, auth, ui },
  middleware: (gDM) => gDM().prepend(listener.middleware).concat(api.middleware),
});

store.subscribe(() => {
  document.documentElement.dataset.theme = store.getState().ui.theme;
});
document.documentElement.dataset.theme = store.getState().ui.theme;
