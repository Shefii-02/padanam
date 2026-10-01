# Padanam Admin (React)

Vite + React 18 + Redux Toolkit (RTK Query) + React Router. Talks to the Laravel API (`/api/v1`) and the Node realtime server (chat).

## Run locally
```bash
cp .env.example .env        # VITE_REALTIME_URL=http://localhost:4000
npm install
npm run dev                  # http://localhost:5173  (proxies /api → php artisan serve on :8000)
npm test                     # smoke test: every page renders with a mocked API
```
Demo login: `admin@padanam.app` / `password` (teacher: `suresh@padanam.app`).

## Build & deploy
```bash
npm run build                # → dist/
```
Nginx (panel on admin.padanam.app, API on the same domain):
```nginx
server {
  server_name admin.padanam.app;
  root /var/www/padanam-admin/dist;
  location /api/     { proxy_pass http://127.0.0.1:8000; proxy_set_header Host $host; }
  location /storage/ { proxy_pass http://127.0.0.1:8000; }
  location /         { try_files $uri /index.html; }
}
```
If the API is on another domain set `VITE_API_URL=https://api.padanam.app` before building, and add the panel domain to Laravel CORS.

## How it's organised
- `src/app/api.js` – one RTK Query API; unwraps `{success, message, data, meta}`, 401 → logout, CSV/PDF `download()`, `toForm()` for uploads.
- `src/app/store.js` – every mutation shows the server message as a toast automatically.
- `src/app/menu.js` + `App.jsx` – sidebar items and routes are hidden/blocked by permission (`permissions` from `/auth/me`, refreshed on each load).
- `src/components` – Layout, ui (Button, Card, Modal, Tabs, Toggle…), `SchemaForm` (all create/edit dialogs), DataTable, Filters.
- `src/features/<module>` – `api.js` (endpoints) + pages: dashboard, catalog, courses (builder: content/batches/details/staff access/students), live, qbank (editor + CSV/Word import), tests (builder, auto-pick, results, OMR), daily (quiz calendar + study plans), articles/doubts, students/admissions, money (payments, coupons, revenue share), grow (notifications, leads, reports), admin (roles matrix, staff, settings), chat (groups, live chat via socket, reports, chat rules).
