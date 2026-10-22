// Module build: run from the repository root so Vite and @vitejs/plugin-vue
// resolve from the host's node_modules (the module carries no toolchain of
// its own):
//
//   pnpm exec vite build --config Modules/WhatsappShare/vite.config.js
//
// Output (dist/whatsapp-share.es.js + dist/style.css) is committed inside
// the module; the service provider registers both with the host shell and
// installs never need a build step.
import { createRequire } from 'module'
import { resolve } from 'path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const require = createRequire(import.meta.url)

/**
 * Externalize Vue to the host's copy (window.Vue, set by the host's
 * main.js). Bundling a second Vue copy breaks reactivity across the
 * boundary: the host renderer would never see the module's ref updates.
 * The shim re-exports every public Vue export from window.Vue; the
 * export list is read from the installed Vue package at build time so
 * compiler-generated imports can never outrun it.
 */
function vueExternalShim() {
  const exportNames = Object.keys(require('vue')).filter((name) => name !== 'default')

  return {
    name: 'vue-external-shim',
    enforce: 'pre',
    resolveId(id) {
      if (id === 'vue') {
        return '\0vue-shim'
      }
    },
    load(id) {
      if (id === '\0vue-shim') {
        return [
          'const V = window.Vue;',
          'export default V;',
          ...exportNames.map((name) => `export const ${name} = V.${name};`),
        ].join('\n')
      }
    },
  }
}

export default defineConfig({
  // No public dir: the default would copy the host's public/ into dist/.
  publicDir: false,
  // The bundle runs in the browser; Vue and axios probe process.env.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    lib: {
      entry: resolve(__dirname, 'Resources/scripts/module.js'),
      name: 'WhatsappShare',
      fileName: (format) => `whatsapp-share.${format}.js`,
      cssFileName: 'style',
      formats: ['es'],
    },
    outDir: resolve(__dirname, 'dist'),
    emptyOutDir: true,
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, '../../resources'),
      '~': resolve(__dirname, 'Resources'),
    },
  },
  plugins: [vueExternalShim(), vue()],
})
