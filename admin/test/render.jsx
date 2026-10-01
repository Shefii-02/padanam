import { createRoot } from 'react-dom/client';
import { act } from 'react';
import { Provider } from 'react-redux';
import { MemoryRouter } from 'react-router-dom';

let root; let el;
export async function render(path) {
  vi.resetModules();
  const { store } = await import('../src/app/store');
  const { default: App } = await import('../src/App');
  el = document.createElement('div');
  document.body.appendChild(el);
  root = createRoot(el);
  await act(async () => { root.render(<Provider store={store}><MemoryRouter initialEntries={[path]}><App /></MemoryRouter></Provider>); });
  const settle = async () => { for (let i = 0; i < 12; i++) { await act(async () => { await new Promise((r) => setTimeout(r, 40)); }); } };
  return { container: el, settle };
}
export function cleanup() { act(() => root?.unmount()); el?.remove(); }
