import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';
import axios from 'axios';

axios.defaults.withCredentials = true;
axios.interceptors.request.use(config => {
    const tok = localStorage.getItem('token');
    if (tok) config.headers['Authorization'] = 'Bearer ' + tok;
    return config;
});

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');
