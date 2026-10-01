import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    // local dev: forward API calls to `php artisan serve`
    proxy: { '/api': 'http://127.0.0.1:8000', '/storage': 'http://127.0.0.1:8000' },
  },
  build: { chunkSizeWarningLimit: 900 },
});
