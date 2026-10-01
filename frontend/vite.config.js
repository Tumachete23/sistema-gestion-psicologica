import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    watch: { usePolling: process.env.VITE_USE_POLLING === 'true' },
    proxy: { '/api': { target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:18000' } },
  },
});
