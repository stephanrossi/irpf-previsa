import { defineConfig, loadEnv } from 'vite';
import os from 'os';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

function detectLanHost(preferredInterface) {
    const interfaces = os.networkInterfaces();
    const pickable = [];

    for (const [name, addrs] of Object.entries(interfaces)) {
        if (preferredInterface && name !== preferredInterface) continue;
        for (const addr of addrs ?? []) {
            if (addr.family === 'IPv4' && !addr.internal) {
                pickable.push(addr.address);
            }
        }
    }

    return pickable[0];
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = Number(env.VITE_DEV_PORT || 5173);

    // Pick host: explicit env > detected LAN IP > fallback localhost
    const detectedLan = detectLanHost(env.VITE_DEV_INTERFACE);
    const lanHost = env.VITE_DEV_HOST || detectedLan || 'localhost';

    // Use explicit DEV_SERVER_URL if provided; otherwise build from detected host
    const origin = env.VITE_DEV_SERVER_URL || `http://${lanHost}:${port}`;

    const hmr = {
        host: lanHost,
        port,
    };

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0', // listen on all interfaces
            port,
            origin,
            hmr,
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
