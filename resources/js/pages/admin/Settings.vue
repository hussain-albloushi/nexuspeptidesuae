<template>
    <div>
        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">Store timezone</h3></div>
            <div class="card-body">
                <p class="text-secondary small">
                    Every date and time recorded by the website — orders, status history, returns, inventory
                    movements — uses this clock and is stored exactly as shown here. The database clock is
                    never used. Changing the timezone applies to new records; existing records keep the
                    wall time they were written with.
                </p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-timezone">Timezone</label>
                        <select id="setting-timezone" v-model="form.timezone" class="form-select">
                            <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                        </select>
                        <small class="text-secondary">All dates in the admin panel and storefront are shown in this timezone.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">Email sender</h3></div>
            <div class="card-body">
                <p class="text-secondary small">
                    Identity used on emails sent to customers. SMTP credentials stay in the server's
                    <code>.env</code> file; they are not stored here.
                </p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-from-name">Sender name</label>
                        <input id="setting-mail-from-name" v-model="form.mail_from_name" class="form-control" maxlength="255" placeholder="Nexus Peptides Support" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-from-address">Sender address</label>
                        <input id="setting-mail-from-address" v-model="form.mail_from_address" type="email" class="form-control" maxlength="255" placeholder="support@nexuspeptides.com" />
                    </div>
                </div>
                <button type="button" :disabled="saving" @click="save" class="btn btn-primary">
                    {{ saving ? 'Saving…' : 'Save system settings' }}
                </button>
            </div>
        </section>

        <p class="text-secondary small">
            Storefront wording, brand details, SEO, VAT, shipping and cash-on-delivery settings are managed
            under <router-link to="/admin/contents">Website Content</router-link>.
        </p>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const timezones = [
    'Asia/Dubai',
    'UTC',
    'Asia/Abu_Dhabi',
    'Asia/Riyadh',
    'Asia/Qatar',
    'Asia/Kuwait',
    'Asia/Bahrain',
    'Asia/Muscat',
    'Asia/Kolkata',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
    'America/Los_Angeles',
    'Australia/Sydney',
];

const form = ref({ timezone: 'Asia/Dubai', mail_from_name: '', mail_from_address: '' });
const saving = ref(false);

function load() {
    axios.get('/api/settings').then(({ data }) => {
        form.value = {
            timezone: data.timezone || 'Asia/Dubai',
            mail_from_name: data.mail_from_name || '',
            mail_from_address: data.mail_from_address || '',
        };
    }).catch(() => {});
}

function save() {
    saving.value = true;
    axios.post('/api/settings', { ...form.value })
        .then(({ data }) => {
            // Keep date rendering on this page in sync until the next reload.
            if (data.timezone && typeof window !== 'undefined') window.__SITE_TIMEZONE__ = data.timezone;
        })
        .catch(() => {})
        .finally(() => { saving.value = false; });
}

onMounted(() => { load(); });
</script>
