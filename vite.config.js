import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

const projectFile = (path) => fileURLToPath(new URL(path, import.meta.url));

export default defineConfig({
    // Hostinger may invoke the build from a parent directory. Pin Vite to this
    // repository so Laravel entrypoints are resolved consistently.
    root: fileURLToPath(new URL('.', import.meta.url)),
    plugins: [
        laravel({
            input: [projectFile('resources/css/app.css'), projectFile('resources/js/app.js')],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
