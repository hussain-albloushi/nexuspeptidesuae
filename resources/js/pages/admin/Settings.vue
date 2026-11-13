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
            <div class="card-header"><h3 class="card-title">Email delivery</h3></div>
            <div class="card-body">
                <p class="text-secondary small">
                    Identity used on emails sent to customers. The mail server fields below override the
                    server's <code>.env</code> values when filled — leave any field blank to keep its
                    current <code>.env</code> setting. The SMTP password is stored as a secret and is never
                    shown again after saving.
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
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-help-delivery">Help Center delivery inbox</label>
                        <input id="setting-help-delivery" v-model="form.contact_delivery_email" type="email" class="form-control" maxlength="255" placeholder="support@nexuspeptides.com" />
                        <small class="text-secondary">New contact-form messages are sent here; blank keeps the support email published under Website Content.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-transport">Transport</label>
                        <select id="setting-mail-transport" v-model="form.mail_transport" class="form-select">
                            <option value="">Keep .env default</option>
                            <option value="smtp">SMTP</option>
                            <option value="log">Log (dev — writes to storage/logs)</option>
                            <option value="array">Array (dev — discards)</option>
                            <option value="sendmail">Sendmail</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-host">SMTP host</label>
                        <input id="setting-mail-host" v-model="form.mail_host" class="form-control" maxlength="255" placeholder="smtp.gmail.com" />
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-port">Port</label>
                        <input id="setting-mail-port" v-model="form.mail_port" type="number" min="1" max="65535" class="form-control" placeholder="587" />
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-scheme">Encryption</label>
                        <select id="setting-mail-scheme" v-model="form.mail_scheme" class="form-select">
                            <option value="">Keep .env default</option>
                            <option value="tls">STARTTLS</option>
                            <option value="ssl">SMTPS</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-username">SMTP username</label>
                        <input id="setting-mail-username" v-model="form.mail_username" class="form-control" maxlength="255" autocomplete="off" placeholder="you@example.com" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-mail-password">SMTP password</label>
                        <input
                            id="setting-mail-password"
                            v-model="form.mail_password"
                            type="password"
                            class="form-control"
                            maxlength="255"
                            :placeholder="mailPasswordStored ? 'Stored •••••• — paste a new one to replace' : 'App password (server side only)'"
                            autocomplete="new-password"
                        />
                        <small class="text-secondary">Gmail users: generate an App Password. Leave blank to keep the stored or .env value.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">Bot protection (Google reCAPTCHA)</h3></div>
            <div class="card-body">
                <p class="text-secondary small">
                    Guards the Help Center contact form against spam. Create a site at
                    <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">google.com/recaptcha/admin</a>
                    (version 3 invisible or version 2 checkbox both work) and paste the two keys here.
                    Add every domain the form runs on, including <code>localhost</code> for testing.
                    When protection is active, visitors complete a silent check before their message is sent.
                </p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-recaptcha-enabled">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input id="setting-recaptcha-enabled" class="form-check-input" type="checkbox" v-model="form.recaptcha_enabled" />
                            <label class="form-check-label small" for="setting-recaptcha-enabled">
                                {{ form.recaptcha_enabled ? 'Enabled — contact form requires a token' : 'Disabled — form submits without verification' }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-recaptcha-site-key">Site key</label>
                        <input id="setting-recaptcha-site-key" v-model="form.recaptcha_site_key" class="form-control" maxlength="255" placeholder="6L… (public, starts with 6L or 6F)" autocomplete="off" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-uppercase small fw-semibold" for="setting-recaptcha-secret-key">Secret key</label>
                        <input
                            id="setting-recaptcha-secret-key"
                            v-model="form.recaptcha_secret_key"
                            type="password"
                            class="form-control"
                            maxlength="255"
                            :placeholder="secretKeyStored ? 'Stored •••••• — paste a new one to replace' : 'G… (server side only)'"
                            autocomplete="new-password"
                        />
                        <small class="text-secondary">Stored as a secret: never cached, never returned to a browser.</small>
                    </div>
                </div>
            </div>
        </section>

        <p class="text-secondary small">
            Storefront wording, brand details, SEO, VAT, shipping and cash-on-delivery settings are managed
            under <router-link to="/admin/contents">Website Content</router-link>.
        </p>

        <div class="card-footer sticky-save d-flex flex-wrap align-items-center gap-3">
            <span v-if="dirty" class="text-warning fw-semibold">
                <i class="bi bi-exclamation-circle me-1"></i>Unsaved changes
            </span>
            <span v-else class="text-secondary small">
                <i class="bi bi-check-circle me-1"></i>All system settings are saved.
            </span>

            <button type="button" class="btn btn-primary ms-auto" :disabled="saving" @click="save">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-check2-circle me-1"></i>
                {{ saving ? 'Saving…' : 'Save system settings' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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

const form = ref({
    timezone: 'Asia/Dubai',
    mail_from_name: '',
    mail_from_address: '',
    contact_delivery_email: '',
    mail_transport: '',
    mail_host: '',
    mail_port: '',
    mail_scheme: '',
    mail_username: '',
    mail_password: '',
    recaptcha_enabled: true,
    recaptcha_site_key: '',
    recaptcha_secret_key: '',
});
const secretKeyStored = ref(false);
const mailPasswordStored = ref(false);
const saving = ref(false);

let savedSnapshot = '';
const dirty = computed(() => JSON.stringify(form.value) !== savedSnapshot);

function snapshot() {
    savedSnapshot = JSON.stringify(form.value);
}

function warnBeforeLeave(event) {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
}

function load() {
    axios.get('/api/settings').then(({ data }) => {
        secretKeyStored.value = Boolean(data.recaptcha_secret_key_set);
        mailPasswordStored.value = Boolean(data.mail_password_set);
        form.value = {
            timezone: data.timezone || 'Asia/Dubai',
            mail_from_name: data.mail_from_name || '',
            mail_from_address: data.mail_from_address || '',
            contact_delivery_email: data.contact_delivery_email || '',
            mail_transport: data.mail_transport || '',
            mail_host: data.mail_host || '',
            mail_port: data.mail_port || '',
            mail_scheme: data.mail_scheme || '',
            mail_username: data.mail_username || '',
            mail_password: '',
            recaptcha_enabled: data.recaptcha_enabled !== '0',
            recaptcha_site_key: data.recaptcha_site_key || '',
            recaptcha_secret_key: '',
        };
        snapshot();
    }).catch(() => {});
}

function save() {
    saving.value = true;
    axios.post('/api/settings', { ...form.value })
        .then(({ data }) => {
            // Keep date rendering on this page in sync until the next reload.
            if (data.timezone && typeof window !== 'undefined') window.__SITE_TIMEZONE__ = data.timezone;
            if (form.value.recaptcha_secret_key) secretKeyStored.value = true;
            if (form.value.mail_password) mailPasswordStored.value = true;
            form.value.recaptcha_secret_key = '';
            form.value.mail_password = '';
            snapshot();
        })
        .catch(() => {})
        .finally(() => { saving.value = false; });
}

onMounted(() => {
    load();
    window.addEventListener('beforeunload', warnBeforeLeave);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeLeave);
});
</script>

<style scoped>
/* Same sticky save bar as the Website Content editor: stays pinned at the
   viewport bottom so long settings pages save without scrolling back. */
.sticky-save {
    position: sticky;
    bottom: 0;
    z-index: 2;
    background: var(--admin-inset);
    border: 1px solid var(--admin-border);
    border-radius: 13px;
    box-shadow: 0 -6px 16px #0000000d;
}
</style>
