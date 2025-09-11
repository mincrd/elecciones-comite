import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path' // Asegúrate de que 'path' esté importado

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      // Esta es la línea clave que define el alias '@'
      '@': path.resolve(__dirname, './src'),
    }
  }
})