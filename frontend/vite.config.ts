
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: '../public/build',
    emptyOutDir: false,
    manifest: true,
    assetsDir: 'assets'
  },
  server: { port: 5173, strictPort: true }
})
