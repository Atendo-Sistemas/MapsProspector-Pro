import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  // Carrega variáveis de ambiente baseadas no modo (ex: .env)
  // O terceiro parâmetro '' permite carregar todas as variáveis, não apenas as com prefixo VITE_
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [react()],
    root: './',
    base: './',
    define: {
      // INJEÇÃO CRÍTICA: Mapeia process.env.API_KEY para a variável de ambiente do sistema
      // Isso permite que o SDK do Google GenAI funcione no navegador com a chave do servidor
      'process.env.API_KEY': JSON.stringify(env.API_KEY || process.env.API_KEY),
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
      host: true
    }
  };
});
