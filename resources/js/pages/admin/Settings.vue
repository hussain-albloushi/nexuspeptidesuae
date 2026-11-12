<template>
    <div>
        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">System settings</h3></div>
            <div class="card-body">
                <p class="text-secondary small">Storefront wording, brand details, SEO, and shipping &amp; cash-on-delivery settings are managed under <router-link to="/admin/contents">Website Content</router-link>.</p>
                <div class="row">
                    <div v-for="key in keys" :key="key" class="col-md-4 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" :for="'setting-' + key">{{ key.replace(/_/g, ' ') }}</label>
                        <input :id="'setting-' + key" v-model="form[key]" class="form-control" :placeholder="key" />
                    </div>
                </div>
                <button type="button" :disabled="saving" @click="save" class="btn btn-primary">{{ saving ? 'Saving…' : 'Save system settings' }}</button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const keys = ['timezone', 'tax_settings', 'payment_methods', 'email_settings', 'store_policies'];
const form = ref({});
const saving = ref(false);

function load() {
    axios.get('/api/settings').then(({ data }) => {
        form.value = Object.fromEntries(keys.map(key => [key, data[key] || '']));
    }).catch(() => {});
}
function save() {
    saving.value = true;
    axios.post('/api/settings', Object.fromEntries(keys.map(key => [key, form.value[key]]))).catch(() => {}).finally(() => { saving.value = false; });
}
onMounted(() => { load(); });
</script>
