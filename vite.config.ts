import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwind from '@tailwindcss/vite';
import path from 'node:path';
export default defineConfig({
  plugins: [...(process.env.VITEST?[]:[laravel({input:['resources/js/app.tsx'],refresh:true})]),react(),tailwind()],
  resolve:{alias:{'@':path.resolve(import.meta.dirname,'resources/js')}},
  test:{environment:'jsdom',setupFiles:['resources/js/test-setup.ts'],include:['resources/js/**/*.test.{ts,tsx}']},
});
