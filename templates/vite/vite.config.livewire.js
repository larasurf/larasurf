import { defineConfig, loadEnv } from 'vite';
import laravel, { refreshPaths } from 'laravel-vite-plugin';

export default defineConfig(({ command, mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = Number.parseInt(env.SURF_VITE_HMR_PORT);
    const useTls = env.SURF_USE_TLS === 'true';

    return {
        server: {
            watch: {
                usePolling: true,
            },
            port: 5173,
            strictPort: true,
            cors: false,
            https: useTls
                ? {
                    servername: 'localhost',
                    key: '/var/tls/local.pem',
                    cert: '/var/tls/local.crt',
                }
                : false,
            host: '0.0.0.0',
            hmr: {
                protocol: useTls ? 'wss' : 'ws',
                clientPort: port,
                host: 'localhost',
                port: 5173,
            },
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                ],
                refresh: [
                    ...refreshPaths,
                    'app/Http/Livewire/**',
                ],
            }),
        ],
    };
});
