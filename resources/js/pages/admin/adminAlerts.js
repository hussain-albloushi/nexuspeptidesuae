import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

export function adminAlertOptions(options = {}) {
    const dialogs = [...document.querySelectorAll('dialog[open]')];
    return {
        target: dialogs.at(-1) || document.querySelector('.apex-admin-shell') || document.body,
        background: 'var(--admin-surface, #fff)',
        color: 'var(--admin-text, #060d11)',
        confirmButtonColor: '#008e4c',
        cancelButtonColor: '#56616a',
        customClass: { container: 'admin-swal-container', popup: 'admin-swal-popup' },
        heightAuto: false,
        ...options,
    };
}

export async function confirmAdmin({ title, text, confirmButtonText = 'Confirm', destructive = false, ...options }) {
    return Swal.fire(adminAlertOptions({
        title, text, icon: destructive ? 'warning' : 'question',
        showCancelButton: true, cancelButtonText: 'Cancel',
        confirmButtonText, focusCancel: true,
        confirmButtonColor: destructive ? '#b91c1c' : '#008e4c',
        allowOutsideClick: () => !Swal.isLoading(),
        allowEscapeKey: () => !Swal.isLoading(),
        ...options,
    }));
}

const notifications = [];
let notificationTimer;
const recent = new Map();

function drainNotifications() {
    clearTimeout(notificationTimer);
    if (!notifications.length) return;
    if (Swal.isVisible()) {
        notificationTimer = setTimeout(drainNotifications, 150);
        return;
    }
    const { icon, text } = notifications.shift();
    if (!location.pathname.startsWith('/admin')) {
        notifications.length = 0;
        return;
    }
    Swal.fire(adminAlertOptions({
        toast: true, position: 'top-end', icon, text,
        showConfirmButton: false, showCloseButton: true,
        timer: icon === 'error' ? 7000 : 3500, timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
        didClose: drainNotifications,
    }));
}

export function notifyAdmin(icon, text) {
    if (!text || !location.pathname.startsWith('/admin')) return;
    const key = icon + ':' + text;
    if (Date.now() - (recent.get(key) || 0) < 5000) return;
    recent.set(key, Date.now());
    if (recent.size > 100) recent.delete(recent.keys().next().value);
    notifications.push({ icon, text: String(text) });
    clearTimeout(notificationTimer);
    notificationTimer = setTimeout(drainNotifications, 0);
}

export function installAdminAlerts(axios) {
    return axios.interceptors.response.use((response) => {
        const method = response.config.method?.toLowerCase();
        if (['post', 'put', 'patch', 'delete'].includes(method) && response.config.url?.startsWith('/api/') && !/\/(login|register|logout)$/.test(response.config.url)) {
            notifyAdmin('success', response.data?.message || (method === 'delete' ? 'Record deleted successfully.' : 'Changes saved successfully.'));
        }
        return response;
    }, (failure) => {
        const url = failure.config?.url || '';
        if (!axios.isCancel(failure) && url.startsWith('/api/') && !url.includes('/dashboard/notifications') && !Swal.isLoading()) {
            const data = failure.response?.data;
            const errors = Object.values(data?.errors || {}).flat().join(' ');
            notifyAdmin('error', errors || data?.message || 'The request could not be completed. Please try again.');
        }
        return Promise.reject(failure);
    });
}

export { Swal };
