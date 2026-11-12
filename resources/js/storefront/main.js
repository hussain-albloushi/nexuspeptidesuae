import '../bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import axios from 'axios';
import router from './router';
import { useAuthStore } from './stores/auth';
import { swalToast } from './services/alerts';

// Attach the storefront's Sanctum token to every API request.
const TOKEN_KEY = 'nexus_token';
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

// A 401 with a stored token means the session is no longer valid (the account
// was deleted or signed out everywhere): clear it and send the customer to
// log in again, no matter which call triggered it.
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401 && localStorage.getItem(TOKEN_KEY)) {
            useAuthStore().invalidate();
            if (router.currentRoute.value.name !== 'account') {
                router.push('/my-account');
            }
            swalToast('Your session has ended. Please log in again.', 'error');
        }
        return Promise.reject(error);
    },
);

// Font Awesome (free) — icons across the storefront
import '@fortawesome/fontawesome-free/css/all.min.css';

// SweetAlert2 + its stylesheet, themed in storefront.css
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

import App from './App.vue';
import { swal, swalConfirm } from './services/alerts';

const app = createApp(App);

app.use(createPinia());
app.use(router);

// Access anywhere via this.$swal / this.$swalToast / this.$swalConfirm
app.config.globalProperties.$swal = Swal;
app.config.globalProperties.$swalToast = swalToast;
app.config.globalProperties.$swalConfirm = swalConfirm;

// Re-validate the session once a minute while the tab is visible. A customer
// whose account was deleted (or signed out everywhere) elsewhere is signed out
// automatically without needing to navigate to another page. The 401 the server
// returns lands in the response interceptor, which clears the local session.
setInterval(() => {
    if (document.visibilityState === 'visible') {
        useAuthStore().pingSession({ force: true });
    }
}, 60000);

app.mount('#app');
