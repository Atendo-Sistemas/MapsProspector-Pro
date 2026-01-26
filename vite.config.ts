
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  // Define o diretório raiz como o diretório atual
  root: './',
  // Faz com que os links no HTML fiquem como "./assets/..." em vez de "/assets/..."
  base: './',
  define: {
    // Injeção direta da Chave de API fornecida para garantir funcionamento imediato
    'process.env.API_KEY': JSON.stringify("AIzaSyB-vO2vH6m6G9r7m7v8z2x1c5v4b3n2m1")
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    minify: 'terser',
    rollupOptions: {
      input: {
        main: './index.html'
      }
    }
  },
  server: {
    port: 3000,
    host: true // Permite acesso externo na VPS
  }
});
