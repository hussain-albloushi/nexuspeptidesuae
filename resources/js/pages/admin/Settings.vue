<template>
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">System Settings</h3></div>
        <div class="card-body">
            <p class="text-secondary fs-7">
                Commerce and system configuration. Storefront wording, brand details, and SEO are
                managed under
                <router-link to="/admin/contents">Website Content</router-link>.
            </p>
            <div class="row">
                <div v-for="k in keys" :key="k" class="col-md-4 mb-3">
                    <label class="form-label text-uppercase fs-7 fw-semibold">{{ k.replace(/_/g, ' ') }}</label>
                    <input v-model="form[k]" class="form-control" :placeholder="k" />
                </div>
            </div>
            <button :disabled="saving" @click="save" class="btn btn-primary">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                Save Settings
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const keys = [
    'timezone',
    'shipping_settings',
    'tax_settings',
    'payment_methods',
    'email_settings',
    'store_policies',
];
const form = ref({});
const saving = ref(false);

function load() {
    axios
        .get('/api/settings')
        .then((r) => Object.assign(form.value, r.data || {}))
        .catch(() => {});
}

function save() {
    saving.value = true;
    axios
        .post('/api/settings', form.value)
        .then(() => alert('Settings saved successfully.'))
        .catch(() => alert('Failed to save settings.'))
        .finally(() => {
            saving.value = false;
        });
}

onMounted(load);
</script>
