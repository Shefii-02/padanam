import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
export default defineConfig({ plugins: [react()], test: { environment: 'happy-dom', globals: true, testTimeout: 20000, env: { VITE_API_URL: 'http://localhost' } } });
