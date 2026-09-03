import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// ── Transparent CSRF-token retry ────────────────────────────────────────────
// A tab that's been idle (bfcache restore, Edge tab-sleep/discard, or just
// sitting past SESSION_LIFETIME) can hold a stale XSRF-TOKEN cookie. Inertia
// visits already get a graceful "Session Expired" overlay (see app.js's
// router.on('invalid')), but the many raw axios calls outside Inertia's
// router (chat, RBAC, org structure, signature/PIN completion, driver
// dispatch, ...) would otherwise surface a raw 419 "CSRF token mismatch"
// straight into the caller's catch block. CSRF verification runs before the
// controller, so a 419'd request never mutated anything server-side —
// retrying once after reminting the token pair is always safe.
import { sessionExpired } from './Composables/useSession';

let csrfRefresh = null;

window.axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status !== 419 || error.config?.__retriedAfter419) {
            return Promise.reject(error);
        }

        error.config.__retriedAfter419 = true;

        try {
            csrfRefresh ??= window.axios.get(window.location.href).finally(() => { csrfRefresh = null; });
            await csrfRefresh;
            return await window.axios.request(error.config);
        } catch (retryError) {
            if (retryError.response?.status === 419) {
                sessionExpired.value = true;
            }
            return Promise.reject(retryError);
        }
    }
);

// ── Laravel Echo + Soketi (Pusher-compatible WebSocket) ───────────────────
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster:       'pusher',
    key:               import.meta.env.VITE_PUSHER_APP_KEY || 'bugsaymis-app-key',
    wsHost:            import.meta.env.VITE_PUSHER_HOST       || window.location.hostname,
    wsPort:            Number(import.meta.env.VITE_PUSHER_PORT ?? 9601),
    wssPort:           Number(import.meta.env.VITE_PUSHER_PORT ?? 9601),
    httpHost:          import.meta.env.VITE_PUSHER_HOST       || window.location.hostname,
    httpPort:          Number(import.meta.env.VITE_PUSHER_PORT ?? 9601),
    cluster:           import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    forceTLS:          (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
    // Try WebSocket first; fall back to HTTP polling if WebSocket is blocked (e.g. Cloudflare WAF)
    enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling'],
    disableStats:      true,
});

// Interceptor: capture server error responses for vehicle-requests and show raw body in console
window.axios.interceptors.response.use(
	response => response,
	error => {
		try {
			const req = error.config?.url || '';
			if (req.includes('/vehicle-requests')) {
				console.error('Server response for', req, error.response?.status, error.response?.data);
				// expose lastVehicleRequestError globally for UI to read if necessary
				window.lastVehicleRequestError = {
					status: error.response?.status,
					data: error.response?.data,
				};
			}
		} catch (e) {
			console.error('Error in axios interceptor', e);
		}
		return Promise.reject(error);
	}
);
