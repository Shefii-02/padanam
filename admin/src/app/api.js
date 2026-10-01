import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';

export const API_URL = (import.meta.env.VITE_API_URL || '') + '/api/v1';

/** Laravel-style query strings: arrays as key[]=a&key[]=b */
export const paramsSerializer = (params) => {
  const u = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (Array.isArray(v)) v.forEach((x) => u.append(`${k}[]`, x));
    else if (v !== undefined && v !== null && v !== '') u.append(k, v);
  });
  return u.toString();
};

const raw = fetchBaseQuery({
  baseUrl: API_URL,
  paramsSerializer,
  prepareHeaders: (headers, { getState }) => {
    const token = getState().auth.token;
    if (token) headers.set('Authorization', `Bearer ${token}`);
    headers.set('Accept', 'application/json');
    return headers;
  },
});

/** Unwraps Laravel's { success, message, data, meta } envelope; keeps message + meta on the result meta. */
const baseQuery = async (args, api, extra) => {
  const res = await raw(args, api, extra);
  if (res.error) {
    if (res.error.status === 401 && !String(args.url || args).includes('auth/login')) {
      api.dispatch({ type: 'auth/logout' });
    }
    const body = res.error.data || {};
    return { error: { status: res.error.status, message: body.message || 'Something went wrong. Please try again.', errors: body.errors || {} } };
  }
  const body = res.data || {};
  return { data: { data: body.data, meta: body.meta || {} }, meta: { message: body.message } };
};

export const TAGS = ['Me', 'Dashboard', 'Category', 'Course', 'Batch', 'Folder', 'Content', 'Live', 'Question', 'QFolder', 'Label', 'Import',
  'Test', 'Daily', 'Plan', 'Article', 'Doubt', 'Student', 'Staff', 'Role', 'Order', 'Coupon', 'Enrollment', 'Revenue', 'Campaign',
  'Template', 'Lead', 'Chat', 'ChatPolicy', 'Settings', 'Version', 'WhatsApp', 'WaMessage', 'Swap', 'Security', 'Export'];

export const api = createApi({
  reducerPath: 'api',
  baseQuery,
  tagTypes: TAGS,
  endpoints: () => ({}),
});

/** transformResponse helpers */
export const one = (r) => r.data;
export const list = (r) => ({ items: r.data || [], meta: r.meta || {} });

/** Removes empty values so filters don't send ?status= */
export const clean = (o = {}) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v !== null && v !== undefined && !(Array.isArray(v) && !v.length)));

/** Authenticated file download (CSV / PDF). */
export async function download(path, params = {}, filename) {
  const token = localStorage.getItem('pdn_token');
  const qs = paramsSerializer(clean(params));
  const res = await fetch(`${API_URL}/${path}${qs ? `?${qs}` : ''}`, { headers: { Authorization: `Bearer ${token}` } });
  if (!res.ok) throw new Error('Download failed');
  const blob = await res.blob();
  const cd = res.headers.get('Content-Disposition') || '';
  const name = filename || (cd.match(/filename="?([^";]+)/) || [])[1] || 'download';
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = name;
  a.click();
  URL.revokeObjectURL(a.href);
}

/** multipart helper: turns an object into FormData (arrays → key[]) */
export function toForm(obj) {
  const fd = new FormData();
  const add = (k, v) => {
    if (v === undefined || v === null) return;
    if (v instanceof File || v instanceof Blob) fd.append(k, v);
    else if (Array.isArray(v)) v.forEach((x, i) => (typeof x === 'object' && !(x instanceof File) ? Object.entries(x).forEach(([kk, vv]) => add(`${k}[${i}][${kk}]`, vv)) : add(`${k}[]`, x)));
    else if (typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => add(`${k}[${kk}]`, vv));
    else if (typeof v === 'boolean') fd.append(k, v ? '1' : '0');
    else fd.append(k, v);
  };
  Object.entries(obj).forEach(([k, v]) => add(k, v));
  return fd;
}
