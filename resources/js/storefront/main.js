import '../bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';

// Font Awesome (free) — icons across the storefront
import '@fortawesome/fontawesome-free/css/all.min.css';

// SweetAlert2 + its stylesheet, themed in storefront.css
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

import router from './router';
import App from './App.vue';
import { swal, swalToast, swalConfirm } from './services/alerts';

const app = createApp(App);

app.use(createPinia());
app.use(router);

// Access anywhere via this.$swal / this.$swalToast / this.$swalConfirm
app.config.globalProperties.$swal = Swal;
app.config.globalProperties.$swalToast = swalToast;
app.config.globalProperties.$swalConfirm = swalConfirm;

app.mount('#app');
