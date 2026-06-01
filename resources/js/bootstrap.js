// CSRF token for non-Inertia fetch requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.csrfToken = token.content;
}

// Laravel Echo + Reverb WebSocket
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster:        'reverb',
    key:                import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:             import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1',
    wsPort:             import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort:            import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS:           (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports:  ['ws', 'wss'],
});
