import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: 'resources/js/tests/setup.ts',
        include: ['resources/js/**/*.spec.ts'],
        coverage: {
            reporter: ['text', 'lcov', 'html'],
            include: ['resources/js/**/*.{ts,vue}'],
            exclude: [
                'resources/js/tests/**',
                'resources/js/**/__tests__/**',
                'resources/js/**/__mocks__/**',
                'resources/js/app.ts',
                'resources/js/ssr.ts',
            ],
        },
    },
});
