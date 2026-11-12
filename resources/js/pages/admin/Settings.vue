<template>
    <div>
        <section class="card mb-4" aria-labelledby="shipping-settings-title">
            <header class="card-header"><h3 id="shipping-settings-title" class="card-title">Shipping &amp; cash on delivery</h3></header>
            <div class="card-body">
                <p class="text-secondary">These settings control shipping in the cart, checkout, and saved orders. Free shipping applies at or above the minimum item subtotal, before coupons, VAT, and shipping. The cash on delivery (COD) fee is added once per order when the customer pays cash on delivery.</p>
                <p v-if="shippingLoading" role="status">Loading shipping settings…</p>
                <div v-else-if="shippingLoadError" class="alert alert-danger" role="alert">{{ shippingLoadError }} <button type="button" class="btn btn-sm btn-outline-secondary" @click="loadShipping">Retry</button></div>
                <form v-else @submit.prevent="saveShipping">
                    <div v-if="shippingError" class="alert alert-danger" role="alert">{{ shippingError }}</div>
                    <p v-if="!storePublished" class="alert alert-warning">The Store content group is unpublished. Publish it in Website Content before changing live shipping settings.</p>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="shipping-mode">Shipping mode</label><select id="shipping-mode" v-model="shipping.shippingMode" class="form-select" :disabled="shippingSaving"><option value="flat">Charge below the free-shipping minimum</option><option value="free">Always free shipping</option></select></div>
                        <div class="col-md-4"><label class="form-label" for="shipping-rate">Delivery charge (AED)</label><input id="shipping-rate" v-model="shipping.flatRate" type="number" min="0" max="1000000" step="0.01" :required="shipping.shippingMode === 'flat'" class="form-control" :disabled="shippingSaving || shipping.shippingMode === 'free'" /><small class="form-text">Charged when the subtotal is below the minimum.</small></div>
                        <div class="col-md-4"><label class="form-label" for="shipping-minimum">Free-shipping minimum (AED)</label><input id="shipping-minimum" v-model="shipping.freeShippingThreshold" type="number" min="0" max="1000000" step="0.01" class="form-control" :disabled="shippingSaving || shipping.shippingMode === 'free'" /><small class="form-text">Leave blank to disable the minimum-based promotion.</small></div>
                        <div class="col-md-4"><label class="form-label" for="cod-fee">Cash on delivery fee (AED)</label><input id="cod-fee" v-model="shipping.codFee" type="number" min="0" max="1000000" step="0.01" class="form-control" :disabled="shippingSaving" /><small class="form-text">Added once per order when the customer selects cash on delivery. Leave 0 to disable.</small></div>
                    </div>
                    <p class="mt-3 mb-3 small text-secondary" aria-live="polite">{{ shippingSummary }}</p>
                    <button type="submit" class="btn btn-primary" :disabled="shippingSaving || !storePublished">{{ shippingSaving ? 'Saving…' : 'Save settings' }}</button>
                    <router-link to="/admin/contents" class="btn btn-outline-secondary ms-2">Website Content</router-link>
                </form>
            </div>
        </section>
        <section class="card mb-4">
            <div class="card-header"><h3 class="card-title">System settings</h3></div>
            <div class="card-body">
                <p class="text-secondary small">Storefront wording, brand details, and SEO are managed under <router-link to="/admin/contents">Website Content</router-link>.</p>
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
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const keys = ['timezone', 'tax_settings', 'payment_methods', 'email_settings', 'store_policies'];
const form = ref({});
const saving = ref(false);
const shipping = reactive({ shippingMode: 'flat', flatRate: 25, freeShippingThreshold: 300, codFee: 0 });
const shippingLoading = ref(true);
const shippingSaving = ref(false);
const shippingLoadError = ref('');
const shippingError = ref('');
const storePublished = ref(true);
const shippingSummary = computed(() => {
    const cod = Number(shipping.codFee || 0);
    const codSummary = cod > 0 ? ' Cash on delivery adds AED ' + cod.toFixed(2) + ' per COD order.' : ' No cash on delivery fee.';
    if (shipping.shippingMode === 'free') return 'All orders receive free shipping.' + codSummary;
    const charge = Number(shipping.flatRate || 0).toFixed(2);
    if (shipping.freeShippingThreshold === '' || shipping.freeShippingThreshold === null) return 'Shipping costs AED ' + charge + ' on every order.' + codSummary;
    return 'Free shipping from AED ' + Number(shipping.freeShippingThreshold).toFixed(2) + '; AED ' + charge + ' below that amount.' + codSummary;
});

async function loadShipping() {
    shippingLoading.value = true; shippingLoadError.value = '';
    try {
        const { data } = await axios.get('/api/content-editor');
        const store = data.groups.find(group => group.key === 'store');
        if (!store) throw new Error('Store settings are unavailable.');
        storePublished.value = store.is_active;
        Object.assign(shipping, {
            shippingMode: store.data.shippingMode || 'flat',
            flatRate: store.data.flatRate ?? 25,
            freeShippingThreshold: store.data.freeShippingThreshold ?? '',
            codFee: store.data.codFee ?? 0,
        });
    } catch { shippingLoadError.value = 'Shipping settings could not be loaded.'; }
    finally { shippingLoading.value = false; }
}
async function saveShipping() {
    if (shippingSaving.value) return;
    shippingSaving.value = true; shippingError.value = '';
    try {
        const { data } = await axios.get('/api/content-editor');
        const store = data.groups.find(group => group.key === 'store');
        if (!store?.is_active) throw new Error('Publish the Store group in Website Content before updating live shipping.');
        const settings = {
            shippingMode: shipping.shippingMode,
            flatRate: Number(shipping.flatRate || 0),
            freeShippingThreshold: shipping.freeShippingThreshold === '' || shipping.freeShippingThreshold === null ? null : Number(shipping.freeShippingThreshold),
            codFee: Number(shipping.codFee || 0),
        };
        await axios.put('/api/content-editor/store', { is_active: true, data: { ...store.data, ...settings } });
    } catch (failure) {
        shippingError.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || failure.message || 'Shipping settings could not be saved.';
    } finally { shippingSaving.value = false; }
}
function load() {
    axios.get('/api/settings').then(({ data }) => {
        form.value = Object.fromEntries(keys.map(key => [key, data[key] || '']));
    }).catch(() => {});
}
function save() {
    saving.value = true;
    axios.post('/api/settings', Object.fromEntries(keys.map(key => [key, form.value[key]]))).catch(() => {}).finally(() => { saving.value = false; });
}
onMounted(() => { load(); void loadShipping(); });
</script>
