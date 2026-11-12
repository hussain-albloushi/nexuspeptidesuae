<template>
  <div class="customer-workspace">
    <header class="customer-heading">
      <div><h2>Customers</h2><p>Manage your customer relationships, profiles, and account access.</p></div>
      <div class="customer-actions">
        <button class="customer-button" :disabled="exporting || loading || !pagination.total" @click="exportCustomers('csv')"><AdminIcon name="document" :size="16" /> CSV</button>
        <button class="customer-button" :disabled="exporting || loading || !pagination.total" @click="exportCustomers('excel')" title="Download an Excel-compatible XML spreadsheet"><AdminIcon name="document" :size="16" /> Excel</button>
        <button class="customer-button primary" @click="openEditor()"><span aria-hidden="true">＋</span> Add customer</button>
      </div>
    </header>
    <div v-if="success" class="customer-feedback positive" role="status"><span>{{ success }}</span><button class="customer-button" aria-label="Dismiss success message" @click="success = ''">×</button></div>
    <div v-if="pageError" class="customer-feedback negative" role="alert">{{ pageError }}</div>
    <p v-if="exporting" class="customer-muted" role="status">Preparing your export…</p>
    <section class="customer-panel" aria-labelledby="customer-filter-title">
      <div class="customer-panel-heading"><div><h3 id="customer-filter-title">Customer directory</h3><p>Find people by name, email, or phone.</p></div><button class="customer-button" :disabled="!hasFilters" @click="resetFilters">Reset filters</button></div>
      <form class="customer-filters" role="search" @submit.prevent="loadCustomers">
        <label class="customer-search">Search customers<div class="customer-search-control"><AdminIcon name="search" :size="17" /><input v-model="filters.q" type="search" maxlength="255" placeholder="Name, email, or phone…" /></div></label>
        <label>Status<select v-model="filters.status"><option value="">All statuses</option><option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option></select></label>
        <label>Segment<input v-model="filters.segment" maxlength="100" placeholder="Exact segment, e.g. VIP" /></label>
        <label>Location<input v-model="filters.location" maxlength="255" placeholder="City or country" /></label>
        <label>Registered from<input v-model="filters.start_date" type="date" :max="filters.end_date || undefined" /></label>
        <label>Registered to<input v-model="filters.end_date" type="date" :min="filters.start_date || undefined" /></label>
        <button type="submit" class="visually-hidden">Search customers</button>
      </form>
    </section>

    <section class="customer-panel" aria-labelledby="customer-list-title" :aria-busy="loading">
      <div class="customer-panel-heading"><div><h3 id="customer-list-title">All customers <span class="customer-count">{{ loading ? '…' : pagination.total }}</span></h3><p>Choose a customer to view their profile and history.</p></div><label class="customer-page-size">Rows per page<select v-model.number="perPage"><option v-for="size in [10,20,50,100]" :key="size" :value="size">{{ size }}</option></select></label></div>
      <div v-if="selectedIds.length" class="customer-bulk" aria-label="Bulk customer actions">
        <strong>{{ selectedIds.length }} selected on this page</strong>
        <label class="visually-hidden" for="customer-bulk-status">New customer status</label>
        <select id="customer-bulk-status" v-model="bulkStatus"><option value="">Choose status</option><option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option></select>
        <button class="customer-button primary" :disabled="!bulkStatus || busy" @click="prepareAction('status', selectedCustomers, bulkStatus)">Change status</button>
        <button class="customer-button" :disabled="exporting" @click="exportCustomers('csv', true)">Export CSV</button>
        <button class="customer-button" :disabled="exporting" @click="exportCustomers('excel', true)">Export Excel</button>
        <button class="customer-button danger" :disabled="busy" @click="prepareAction('delete', selectedCustomers)">Delete selected</button>
        <button class="customer-button" @click="selectedIds = []">Clear</button>
      </div>
      <div class="customer-mobile-sort"><label>Sort by<select v-model="sort"><option v-for="column in columns" :key="column.key" :value="column.key">{{ column.label }}</option></select></label><button class="customer-button" @click="direction = direction === 'asc' ? 'desc' : 'asc'">{{ direction === 'asc' ? 'Ascending ↑' : 'Descending ↓' }}</button></div>
      <div class="customer-table-wrap">
        <table class="customer-table">
          <caption class="visually-hidden">Customer directory. Click column headings to sort. Select rows for bulk actions.</caption>
          <thead><tr>
            <th scope="col"><input type="checkbox" :checked="allSelected" :indeterminate="selectedIds.length > 0 && !allSelected" :disabled="loading || !customers.length" aria-label="Select all customers on this page" @change="selectAll" /></th>
            <th v-for="column in columns" :key="column.key" scope="col" :aria-sort="sort === column.key ? direction === 'asc' ? 'ascending' : 'descending' : 'none'"><button class="customer-sort" @click="sortBy(column.key)">{{ column.label }}<span aria-hidden="true">{{ sort === column.key ? direction === 'asc' ? '↑' : '↓' : '↕' }}</span></button></th>
            <th scope="col">Actions</th>
          </tr></thead>
          <tbody v-if="loading"><tr v-for="row in 6" :key="row" class="customer-skeleton-row"><td v-for="cell in 8" :key="cell"><span class="customer-skeleton"></span></td></tr></tbody>
          <tbody v-else-if="loadError"><tr><td colspan="8" class="customer-empty"><div role="alert"><h3>Customers could not be loaded</h3><p>{{ loadError }}</p></div><button class="customer-button" @click="loadCustomers">Try again</button></td></tr></tbody>
          <tbody v-else-if="!customers.length"><tr><td colspan="8" class="customer-empty"><AdminIcon name="users" :size="36" /><h3>{{ hasFilters ? 'No customers match your filters' : 'Your customer directory starts here' }}</h3><p>{{ hasFilters ? 'Try a different search or clear the filters.' : 'Add a customer to manage their profile and orders.' }}</p><button class="customer-button primary" @click="hasFilters ? resetFilters() : openEditor()">{{ hasFilters ? 'Clear filters' : 'Add customer' }}</button></td></tr></tbody>
          <tbody v-else><tr v-for="customer in customers" :key="customer.id" class="customer-row" :class="{ selected: selectedIds.includes(customer.id) }">
            <td class="customer-selection"><input v-model="selectedIds" type="checkbox" :value="customer.id" :aria-label="'Select ' + customer.name" /></td>
            <td class="customer-profile-cell" data-label="Name"><button class="customer-profile" @click="openDetail(customer.id)"><span class="customer-avatar" aria-hidden="true">{{ initials(customer.name) }}</span><span><strong>{{ customer.name }}</strong><small>{{ customer.segment || 'No segment' }}</small></span></button></td>
            <td data-label="Email"><span class="customer-email">{{ customer.email }}</span></td>
            <td data-label="Phone">{{ customer.phone || '—' }}</td>
            <td data-label="Status"><span class="customer-status" :class="customer.customer_status">{{ label(customer.customer_status) }}</span></td>
            <td data-label="Registered">{{ date(customer.created_at) }}</td>
            <td data-label="Last activity">{{ customer.last_activity_at ? date(customer.last_activity_at) : 'Not recorded' }}</td>
            <td class="customer-row-actions"><button class="customer-button compact" :aria-label="'View ' + customer.name" @click="openDetail(customer.id)">View</button><button class="customer-button compact" :aria-label="'Edit ' + customer.name" @click="editCustomer(customer.id)">Edit</button><button class="customer-button compact danger" :aria-label="'Delete ' + customer.name" @click="prepareAction('delete', [customer])"><AdminIcon name="logout" :size="14" /> Delete</button></td>
          </tr></tbody>
        </table>
      </div>
      <footer class="customer-pagination"><span aria-live="polite">{{ loading ? 'Loading customers…' : 'Showing ' + (pagination.from || 0) + '–' + (pagination.to || 0) + ' of ' + pagination.total }}</span><nav aria-label="Customer pagination"><button class="customer-button" :disabled="loading || page <= 1" @click="page--">← Previous</button><span>Page {{ page }} of {{ pagination.last_page }}</span><button class="customer-button" :disabled="loading || page >= pagination.last_page" @click="page++">Next →</button></nav></footer>
    </section>

    <dialog ref="detailDialog" class="customer-dialog customer-drawer" aria-labelledby="customer-detail-title" @cancel.prevent="closeDetail">
      <header class="customer-dialog-heading"><div><h2 id="customer-detail-title">{{ detail?.customer.name || 'Customer profile' }}</h2><p>Profile, orders, and customer activity</p></div><button class="customer-button icon" aria-label="Close customer details" @click="closeDetail">×</button></header>
      <div class="customer-dialog-body">
        <div v-if="detailLoading" role="status"><p>Loading customer details…</p><span v-for="row in 6" :key="row" class="customer-skeleton detail-placeholder"></span></div>
        <div v-else-if="detailError" class="customer-feedback negative" role="alert">{{ detailError }}<button class="customer-button" @click="fetchDetail">Retry</button></div>
        <template v-else-if="detail">
          <div class="customer-detail-banner"><span class="customer-avatar large" aria-hidden="true">{{ initials(detail.customer.name) }}</span><div><strong>{{ detail.customer.email }}</strong><p>{{ detail.customer.location || 'No location recorded' }}</p></div><span class="customer-status" :class="detail.customer.customer_status">{{ label(detail.customer.customer_status) }}</span></div>
          <section class="customer-detail-section"><h3>Profile information</h3><dl class="customer-profile-grid"><div v-for="field in profileFields" :key="field.key"><dt>{{ field.label }}</dt><dd>{{ field.date ? date(detail.customer[field.key], true) : detail.customer[field.key] || 'Not recorded' }}</dd></div></dl></section>
          <section class="customer-detail-section"><h3>Internal notes</h3><p class="customer-notes">{{ detail.customer.customer_notes || 'No notes yet. Edit the customer to add internal notes.' }}</p></section>
          <section class="customer-detail-section"><h3>Order history <span class="customer-count">{{ detail.order_count }}</span></h3><div class="customer-table-wrap"><table class="customer-table"><thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead><tbody><tr v-for="order in detail.orders.data" :key="order.id"><td><router-link :to="{ path: '/admin/orders', query: { q: order.order_number } }" @click="closeDetail">#{{ order.order_number }}</router-link></td><td>{{ date(order.created_at) }}</td><td>{{ label(order.status) }}</td><td>{{ money(order.total_amount) }}</td></tr><tr v-if="!detail.orders.data.length"><td colspan="4">No orders recorded.</td></tr></tbody></table></div><nav v-if="detail.orders.last_page > 1" class="customer-detail-pagination" aria-label="Customer order history pagination"><button class="customer-button" :disabled="orderPage <= 1" @click="changeDetailPage('orders', -1)">Previous</button><span>Page {{ orderPage }} of {{ detail.orders.last_page }}</span><button class="customer-button" :disabled="orderPage >= detail.orders.last_page" @click="changeDetailPage('orders', 1)">Next</button></nav></section>
          <section class="customer-detail-section"><h3>Activity timeline</h3><ol class="customer-timeline"><li v-for="activity in detail.activities.data" :key="activity.id"><strong>{{ activity.description }}</strong><small>{{ date(activity.created_at, true) }} · {{ activity.actor_name || 'System' }}</small></li><li v-if="activityPage === 1 && detail.customer.last_activity_at"><strong>Last authenticated activity</strong><small>{{ date(detail.customer.last_activity_at, true) }}</small></li><li v-if="activityPage === detail.activities.last_page"><strong>Customer registered</strong><small>{{ date(detail.customer.created_at, true) }}</small></li></ol><nav v-if="detail.activities.last_page > 1" class="customer-detail-pagination" aria-label="Customer activity pagination"><button class="customer-button" :disabled="activityPage <= 1" @click="changeDetailPage('activities', -1)">Previous</button><span>Page {{ activityPage }} of {{ detail.activities.last_page }}</span><button class="customer-button" :disabled="activityPage >= detail.activities.last_page" @click="changeDetailPage('activities', 1)">Next</button></nav></section>
        </template>
      </div>
      <footer class="customer-dialog-footer"><button class="customer-button" @click="closeDetail">Close</button><button v-if="detail && !detailLoading" class="customer-button primary" @click="openEditor(detail.customer)">Edit customer</button></footer>
    </dialog>

    <dialog ref="editorDialog" class="customer-dialog customer-editor" aria-labelledby="customer-editor-title" @cancel.prevent="closeEditor">
      <form @submit.prevent="saveCustomer">
        <header class="customer-dialog-heading"><div><h2 id="customer-editor-title">{{ form.id ? 'Edit customer' : 'Add customer' }}</h2><p>Fields marked * are required.</p></div><button type="button" class="customer-button icon" :disabled="busy" aria-label="Close customer editor" @click="closeEditor">×</button></header>
        <div class="customer-dialog-body">
          <div v-if="formError" ref="formErrorElement" tabindex="-1" class="customer-feedback negative" role="alert">{{ formError }}</div>
          <div class="customer-form-grid">
            <label v-for="field in editFields" :key="field.key" :for="'customer-' + field.key">{{ field.label }}{{ field.required ? ' *' : '' }}<input :id="'customer-' + field.key" v-model="form[field.key]" :type="field.type || 'text'" :required="field.required" :maxlength="field.max" :disabled="busy" :aria-invalid="Boolean(fieldErrors[field.key])" :aria-describedby="fieldErrors[field.key] ? 'customer-error-' + field.key : undefined" /><small v-if="fieldErrors[field.key]" :id="'customer-error-' + field.key" class="customer-field-error">{{ fieldErrors[field.key][0] }}</small></label>
            <label for="customer-edit-status">Status *<select id="customer-edit-status" v-model="form.customer_status" :disabled="busy" required><option v-for="status in statuses" :key="status" :value="status">{{ label(status) }}</option></select></label>
            <label for="customer-password">{{ form.id ? 'New password (optional)' : 'Password *' }}<input id="customer-password" v-model="form.password" type="password" autocomplete="new-password" minlength="8" maxlength="128" :required="!form.id" :disabled="busy" :aria-invalid="Boolean(fieldErrors.password)" /><small class="customer-muted">{{ form.id ? 'Leave blank to keep the existing password.' : 'Use at least 8 characters.' }}</small><small v-if="fieldErrors.password" class="customer-field-error">{{ fieldErrors.password[0] }}</small></label>
            <label class="customer-notes-field" for="customer-notes">Internal notes<textarea id="customer-notes" v-model="form.customer_notes" rows="4" maxlength="10000" :disabled="busy"></textarea></label>
          </div>
          <p class="customer-muted">Inactive, pending, and blocked accounts cannot sign in. Changing an email or password signs out existing sessions.</p>
        </div>
        <footer class="customer-dialog-footer"><button type="button" class="customer-button" :disabled="busy" @click="closeEditor">Cancel</button><button type="submit" class="customer-button primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save customer' }}</button></footer>
      </form>
    </dialog>

    <AdminAlertDialog ref="confirmDialog" :busy="busy" context-class="customer-workspace" @cancel.prevent="closeConfirmation">
      <form @submit.prevent="confirmAction">
        <header class="customer-dialog-heading"><div><h2 id="customer-confirm-title">{{ pending.action === 'delete' ? 'Delete customers?' : 'Change customer status?' }}</h2><p>{{ pending.targets.length }} customer(s) selected</p></div></header>
        <div class="customer-dialog-body">
          <div v-if="actionError" ref="actionErrorElement" tabindex="-1" class="customer-feedback negative" role="alert">{{ actionError }}</div>
          <ul class="customer-confirm-list"><li v-for="customer in pending.targets" :key="customer.id"><strong>{{ customer.name }}</strong><small>{{ customer.email }}</small></li></ul>
          <p v-if="pending.action === 'status'">Set status to <strong>{{ label(pending.status) }}</strong>. {{ pending.status !== 'active' ? 'These accounts will be signed out and cannot sign in.' : 'These customers will be able to sign in.' }}</p>
          <template v-else><p>The selected customers will be permanently deleted and immediately signed out everywhere. Their order records will be preserved.</p><label class="customer-confirm-check"><input v-model="deleteConfirmed" type="checkbox" required :disabled="busy" /> I confirm deletion of these customer records.</label></template>
        </div>
        <footer class="customer-dialog-footer"><button type="button" class="customer-button" :disabled="busy" autofocus @click="closeConfirmation">Go back</button><button type="submit" class="customer-button" :class="pending.action === 'delete' ? 'danger-solid' : 'primary'" :disabled="busy || (pending.action === 'delete' && !deleteConfirmed)">{{ busy ? 'Updating…' : pending.action === 'delete' ? 'Delete customers' : 'Confirm status change' }}</button></footer>
      </form>
    </AdminAlertDialog>
  </div>
</template>

<script setup>
import AdminAlertDialog from './AdminAlertDialog.vue';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { useRoute } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import { formatSiteDate } from '../../siteTime.js';

const route = useRoute();
const statuses = ['active', 'inactive', 'pending', 'blocked'];
const columns = [{ key: 'name', label: 'Name' }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Phone' }, { key: 'customer_status', label: 'Status' }, { key: 'created_at', label: 'Registered' }, { key: 'last_activity_at', label: 'Last activity' }];
const profileFields = [{ key: 'name', label: 'Name' }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Phone' }, { key: 'segment', label: 'Segment' }, { key: 'location', label: 'Location' }, { key: 'created_at', label: 'Registration date', date: true }, { key: 'last_activity_at', label: 'Last activity', date: true }];
const editFields = [{ key: 'name', label: 'Name', required: true, max: 255 }, { key: 'email', label: 'Email', type: 'email', required: true, max: 255 }, { key: 'phone', label: 'Phone', type: 'tel', max: 50 }, { key: 'segment', label: 'Segment', max: 100 }, { key: 'location', label: 'Location', max: 255 }];
const filters = reactive({ q: typeof route.query.q === 'string' ? route.query.q : '', status: '', segment: '', location: '', start_date: '', end_date: '' });
const customers = ref([]);
const loading = ref(true);
const loadError = ref('');
const pageError = ref('');
const success = ref('');
const busy = ref(false);
const exporting = ref(false);
const page = ref(1);
const perPage = ref(20);
const sort = ref('created_at');
const direction = ref('desc');
const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
const selectedIds = ref([]);
const selectedCustomers = computed(() => customers.value.filter(customer => selectedIds.value.includes(customer.id)));
const allSelected = computed(() => customers.value.length > 0 && selectedIds.value.length === customers.value.length);
const hasFilters = computed(() => Object.values(filters).some(value => value.trim() !== ''));
const bulkStatus = ref('');
const detailDialog = ref(null);
const editorDialog = ref(null);
const confirmDialog = ref(null);
const detail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const formError = ref('');
const fieldErrors = ref({});
const formErrorElement = ref(null);
const actionErrorElement = ref(null);
const actionError = ref('');
const form = reactive({});
const pending = ref({ targets: [], action: '', status: '' });
const deleteConfirmed = ref(false);
const orderPage = ref(1);
const activityPage = ref(1);
let detailId;
let detailOpener;
let editorOpener;
let confirmOpener;
let listController;
let detailController;
let listVersion = 0;
let detailVersion = 0;
let timer;
let suppress = false;
let disposed = false;
const label = value => value ? value.charAt(0).toUpperCase() + value.slice(1).replaceAll('_', ' ') : 'Not recorded';
const date = (value, full = false) => formatSiteDate(value, full ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' });
const money = value => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(Number(value || 0));
const initials = name => String(name || '?').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
const message = (failure, fallback) => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || fallback;
const queryParams = () => ({ ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value.trim())), sort: sort.value, direction: direction.value });

async function loadCustomers() {
  clearTimeout(timer);
  listController?.abort();
  listController = new AbortController();
  const version = ++listVersion;
  loading.value = true;
  loadError.value = '';
  selectedIds.value = [];
  customers.value = [];
  try {
    const { data } = await axios.get('/api/customers', { params: { ...queryParams(), page: page.value, per_page: perPage.value }, signal: listController.signal });
    if (disposed || version !== listVersion) return;
    pagination.value = data;
    if (page.value > data.last_page) {
      suppress = true; page.value = Math.max(1, data.last_page); suppress = false;
      return await loadCustomers();
    }
    customers.value = data.data;
  } catch (failure) {
    if (!axios.isCancel(failure) && version === listVersion) loadError.value = message(failure, 'Please try loading the customers again.');
  } finally {
    if (!disposed && version === listVersion) loading.value = false;
  }
}
watch([() => JSON.stringify(filters), perPage, sort, direction, page], (current, previous) => {
  if (suppress || disposed) return;
  if (current.slice(0, 4).some((value, index) => value !== previous[index])) { suppress = true; page.value = 1; suppress = false; }
  clearTimeout(timer); listController?.abort(); listVersion++;
  customers.value = []; selectedIds.value = []; loading.value = true;
  timer = setTimeout(loadCustomers, 250);
}, { flush: 'sync' });
watch(() => route.query.q, value => { filters.q = typeof value === 'string' ? value : ''; });
watch(selectedIds, () => { bulkStatus.value = ''; });
function resetFilters() { Object.keys(filters).forEach(key => { filters[key] = ''; }); }
function sortBy(key) { if (sort.value === key) direction.value = direction.value === 'asc' ? 'desc' : 'asc'; else { sort.value = key; direction.value = 'asc'; } }
function selectAll() { selectedIds.value = allSelected.value ? [] : customers.value.map(customer => customer.id); }
function restoreFocus(element) { if (element?.isConnected) element.focus(); else document.querySelector('.customer-search input')?.focus(); }

async function openDetail(id) {
  detailId = id; orderPage.value = 1; activityPage.value = 1;
  detailOpener = document.activeElement;
  detailDialog.value.showModal();
  await fetchDetail();
}
async function fetchDetail() {
  detailController?.abort(); detailController = new AbortController();
  const version = ++detailVersion;
  detailLoading.value = true; detailError.value = ''; detail.value = null;
  try {
    const { data } = await axios.get('/api/customers/' + detailId, { params: { order_page: orderPage.value, activity_page: activityPage.value }, signal: detailController.signal });
    if (!disposed && version === detailVersion) detail.value = data;
  } catch (failure) {
    if (!axios.isCancel(failure) && version === detailVersion) detailError.value = message(failure, 'Customer details could not be loaded.');
  } finally { if (!disposed && version === detailVersion) detailLoading.value = false; }
}
function closeDetail() { detailController?.abort(); detailVersion++; detailDialog.value.close(); detail.value = null; restoreFocus(detailOpener); }
async function changeDetailPage(section, amount) { if (section === 'orders') orderPage.value += amount; else activityPage.value += amount; await fetchDetail(); }
function openEditor(customer = null) {
  editorOpener = document.activeElement;
  Object.assign(form, { id: customer?.id || null, name: '', email: '', phone: '', customer_status: 'active', segment: '', location: '', customer_notes: '', password: '' }, customer || {}, { password: '' });
  formError.value = ''; fieldErrors.value = {};
  editorDialog.value.showModal();
}
async function editCustomer(id) {
  pageError.value = '';
  try { const { data } = await axios.get('/api/customers/' + id); if (!disposed) openEditor(data.customer); }
  catch (failure) { pageError.value = message(failure, 'Customer could not be loaded for editing.'); }
}
function closeEditor() { if (busy.value) return; editorDialog.value.close(); form.password = ''; restoreFocus(editorOpener); }
async function saveCustomer() {
  if (busy.value) return;
  busy.value = true; formError.value = ''; fieldErrors.value = {}; success.value = '';
  try {
    const payload = Object.fromEntries(['name','email','phone','customer_status','segment','location','customer_notes','password'].map(key => [key, form[key]]));
    if (!payload.password && form.id) delete payload.password;
    const { data } = form.id ? await axios.put('/api/customers/' + form.id, payload) : await axios.post('/api/customers', payload);
    success.value = data.message;
    busy.value = false;
    closeEditor();
    await loadCustomers();
    if (detailDialog.value.open) await fetchDetail();
  } catch (failure) {
    fieldErrors.value = failure.response?.data?.errors || {};
    formError.value = message(failure, 'Customer could not be saved. Please retry.');
    await nextTick(); formErrorElement.value?.focus();
  } finally { busy.value = false; }
}
function prepareAction(action, targets, status = '') {
  if (!targets.length || busy.value) return;
  confirmOpener = document.activeElement;
  pending.value = { action, targets: targets.map(customer => ({ id: customer.id, name: customer.name, email: customer.email })), status };
  deleteConfirmed.value = false; actionError.value = '';
  confirmDialog.value.showModal();
}
function closeConfirmation() { if (busy.value) return; confirmDialog.value.close(); restoreFocus(confirmOpener); }
async function confirmAction() {
  if (busy.value || (pending.value.action === 'delete' && !deleteConfirmed.value)) return;
  busy.value = true; actionError.value = ''; success.value = '';
  try {
    const change = pending.value;
    const { data } = await axios.post('/api/customers/bulk', { ids: change.targets.map(customer => customer.id), action: change.action, ...(change.action === 'status' ? { status: change.status } : { confirmed: true }) });
    success.value = data.message;
    busy.value = false; closeConfirmation();
    await loadCustomers();
  } catch (failure) { actionError.value = message(failure, 'The selected customers could not be updated.'); await nextTick(); actionErrorElement.value?.focus(); }
  finally { busy.value = false; }
}
async function exportCustomers(format, selectedOnly = false) {
  if (exporting.value || (selectedOnly && !selectedIds.value.length)) return;
  exporting.value = true; pageError.value = '';
  try {
    const { data } = await axios.get('/api/customers/export', { params: { ...(selectedOnly ? { ids: selectedIds.value, sort: sort.value, direction: direction.value } : queryParams()), format }, responseType: 'blob' });
    const url = URL.createObjectURL(data);
    const link = document.createElement('a'); link.href = url; link.download = 'customers-' + new Date().toISOString().slice(0, 10) + (format === 'excel' ? '.xml' : '.csv');
    document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    success.value = 'Customer export downloaded' + (format === 'excel' ? '. Open the XML spreadsheet in Excel.' : '.');
  } catch { pageError.value = 'Customer export could not be prepared. Please try again.'; }
  finally { exporting.value = false; }
}
onBeforeUnmount(() => { disposed = true; clearTimeout(timer); listController?.abort(); detailController?.abort(); listVersion++; detailVersion++; editorDialog.value?.close(); detailDialog.value?.close(); confirmDialog.value?.close(); });
void loadCustomers();
</script>

<style scoped>
.customer-workspace { color: var(--admin-text); font: inherit; font-size: 14px; line-height: 1.5; min-width: 0; }
.customer-heading, .customer-panel-heading, .customer-dialog-heading, .customer-pagination, .customer-pagination nav, .customer-dialog-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.customer-heading { margin-bottom: 28px; }.customer-heading h2 { font-size: 24px; line-height: 32px; font-weight: 600; letter-spacing: -.5px; margin: 0 0 4px; }
.customer-heading p, .customer-panel-heading p, .customer-dialog-heading p { color: var(--admin-muted); margin: 0; font-size: 13px; }
.customer-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.customer-button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font: inherit; font-size: 12px; font-weight: 500; border: 1px solid var(--admin-border); border-radius: 8px; min-height: 36px; padding: 7px 12px; background: var(--admin-surface); color: var(--admin-text); cursor: pointer; white-space: nowrap; }
.customer-button:hover { background: var(--admin-inset); border-color: var(--admin-primary); }.customer-button.primary { color: #fff; background: var(--admin-primary); border-color: var(--admin-primary); }.customer-button.danger { color: var(--admin-danger-text); }.customer-button.danger-solid { color: #fff; background: #b91c1c; border-color: #b91c1c; }.customer-button.compact { min-height: 30px; padding: 4px 8px; }.customer-button.icon { font-size: 22px; width: 36px; padding: 0; }
.customer-workspace button:disabled { opacity: .5; cursor: not-allowed; }
.customer-panel { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 14px; box-shadow: 0 1px 3px #0000000d; margin-bottom: 24px; overflow: hidden; }.customer-panel-heading { padding: 20px 24px 16px; }.customer-panel-heading h3 { font-size: 16px; font-weight: 600; margin: 0 0 4px; }
.customer-filters { display: grid; grid-template-columns: 2fr repeat(2, minmax(0, 1fr)); gap: 16px; padding: 4px 24px 24px; }
.customer-workspace label { font-size: 12px; font-weight: 500; display: block; }.customer-workspace input:not([type=checkbox]), .customer-workspace select, .customer-workspace textarea { display: block; width: 100%; min-width: 0; border: 1px solid var(--admin-border); border-radius: 8px; padding: 8px 10px; margin-top: 6px; font: inherit; font-size: 13px; background: var(--admin-surface); color: var(--admin-text); min-height: 38px; }
.customer-workspace input[type=checkbox] { width: 17px; height: 17px; accent-color: var(--admin-primary); cursor: pointer; vertical-align: middle; flex-shrink: 0; }
.customer-search-control { position: relative; }.customer-search-control svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--admin-muted); }.customer-workspace .customer-search-control input { padding-left: 38px; }
.customer-workspace .customer-page-size { display: flex; align-items: center; gap: 10px; color: var(--admin-muted); }.customer-page-size select { width: 74px; margin: 0; }
.customer-count { padding: 2px 7px; border-radius: 6px; background: var(--admin-inset); color: var(--admin-muted); font-size: 12px; margin-left: 5px; font-weight: 500; }
.customer-bulk { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 16px 24px; background: color-mix(in srgb, var(--admin-primary) 6%, var(--admin-surface)); border-block: 1px solid var(--admin-border); }.customer-bulk strong { font-size: 12px; margin-right: 8px; }.customer-bulk select { width: auto; margin: 0; min-height: 36px; }
.customer-table-wrap { overflow-x: auto; }.customer-table { width: 100%; border-collapse: collapse; text-align: left; }.customer-table th { color: var(--admin-muted); font-size: 12px; font-weight: 500; padding: 12px 16px; border-bottom: 1px solid var(--admin-border); white-space: nowrap; }.customer-table td { font-size: 13px; padding: 16px; border-bottom: 1px solid var(--admin-border); vertical-align: middle; }.customer-table tr:last-child td { border-bottom: 0; }.customer-row:hover { background: var(--admin-inset); }.customer-row.selected { background: color-mix(in srgb, var(--admin-primary) 6%, var(--admin-surface)); }
.customer-sort { border: 0; background: none; color: inherit; padding: 0; font: inherit; display: inline-flex; align-items: center; gap: 10px; cursor: pointer; }.customer-sort:hover { color: var(--admin-primary); }
.customer-profile { display: flex; align-items: center; gap: 10px; text-align: left; color: var(--admin-text); background: none; border: 0; padding: 0; cursor: pointer; font: inherit; min-width: 160px; }.customer-profile strong { display: block; font-weight: 500; }.customer-profile:hover strong { color: var(--admin-link); }.customer-profile small, .customer-confirm-list small { display: block; font-size: 11px; color: var(--admin-muted); margin-top: 3px; }.customer-avatar { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; background: color-mix(in srgb, var(--admin-primary) 10%, var(--admin-surface)); color: var(--admin-link); font-size: 12px; font-weight: 600; }.customer-avatar.large { width: 48px; height: 48px; font-size: 17px; }.customer-email { overflow-wrap: anywhere; }
.customer-row-actions { min-width: 180px; }.customer-row-actions .customer-button { margin: 2px; }
.customer-status { --status-color: var(--admin-success-text); display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; color: var(--status-color); background: color-mix(in srgb, var(--status-color) 10%, var(--admin-surface)); white-space: nowrap; }.customer-status::before { content: ''; height: 6px; width: 6px; border-radius: 50%; background: currentColor; }.customer-status.inactive { --status-color: var(--admin-muted); }.customer-status.pending { --status-color: var(--admin-warning-text); }.customer-status.blocked { --status-color: var(--admin-danger-text); }
.customer-pagination { border-top: 1px solid var(--admin-border); padding: 16px 24px; font-size: 12px; color: var(--admin-muted); }.customer-pagination nav { gap: 12px; }
.customer-empty { padding: 48px 24px !important; text-align: center; color: var(--admin-muted); }.customer-empty > svg { margin-bottom: 12px; color: var(--admin-primary); }.customer-empty h3 { color: var(--admin-text); font-size: 16px; font-weight: 600; }.customer-empty p { margin-bottom: 16px; }
.customer-feedback { display: flex; justify-content: space-between; align-items: center; gap: 12px; border: 1px solid var(--admin-border); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; }.customer-feedback.positive { background: color-mix(in srgb, var(--admin-success) 8%, var(--admin-surface)); border-color: var(--admin-success); }.customer-feedback.negative { background: color-mix(in srgb, var(--admin-danger) 6%, var(--admin-surface)); border-color: var(--admin-danger); }
.customer-muted { display: block; color: var(--admin-muted); font-size: 12px; margin-top: 8px; }.customer-field-error { display: block; color: var(--admin-danger-text); font-size: 12px; margin-top: 5px; }
.customer-skeleton { display: block; min-width: 24px; height: 17px; border-radius: 5px; background: linear-gradient(90deg,var(--admin-inset) 20%,var(--admin-border) 50%,var(--admin-inset) 80%); background-size: 200% 100%; animation: customer-shimmer 1.4s linear infinite; }.detail-placeholder { margin: 22px 0; }
.customer-dialog { color: var(--admin-text); background: var(--admin-surface); border: 1px solid var(--admin-border); padding: 0; border-radius: 16px; max-height: 92dvh; width: min(720px, calc(100% - 24px)); margin: auto; }.customer-dialog::backdrop { background: #0007; backdrop-filter: blur(3px); }.customer-dialog-heading { padding: 22px 24px; border-bottom: 1px solid var(--admin-border); flex-wrap: nowrap; align-items: flex-start; }.customer-dialog-heading h2 { font-size: 20px; font-weight: 600; margin: 0 0 4px; overflow-wrap: anywhere; }.customer-dialog-body { padding: 24px; overflow-y: auto; min-height: 0; }.customer-dialog-footer { padding: 16px 24px; border-top: 1px solid var(--admin-border); justify-content: flex-end; background: var(--admin-surface); flex-shrink: 0; }
.customer-drawer { width: min(780px,100%); height: 100dvh; max-height: 100dvh; margin: 0 0 0 auto; border-radius: 16px 0 0 16px; overflow: hidden; }.customer-drawer[open] { display: flex; flex-direction: column; }.customer-drawer .customer-dialog-body { flex: 1; }.customer-dialog-heading { flex-shrink: 0; }
.customer-editor form { display: flex; flex-direction: column; max-height: 92dvh; }.customer-form-grid, .customer-profile-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 18px; }.customer-notes-field { grid-column: 1/-1; }.customer-confirm { width: min(520px,calc(100% - 24px)); }.customer-confirm-list { padding: 0; list-style: none; max-height: 180px; overflow-y: auto; margin: 0 0 20px; }.customer-confirm-list li { padding: 10px 0; border-bottom: 1px solid var(--admin-border); }.customer-workspace .customer-confirm-check { display: flex; align-items: flex-start; gap: 10px; padding: 14px; background: var(--admin-inset); border-radius: 8px; }.customer-confirm-check input { margin-top: 2px; }
.customer-detail-banner { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; }.customer-detail-banner div { flex: 1; min-width: 150px; overflow-wrap: anywhere; }.customer-detail-banner strong { font-weight: 500; }.customer-detail-banner p { color: var(--admin-muted); margin: 3px 0 0; font-size: 13px; }.customer-detail-section { padding-block: 22px; border-top: 1px solid var(--admin-border); }.customer-detail-section h3 { font-size: 15px; font-weight: 600; margin: 0 0 18px; }.customer-profile-grid { margin: 0; }.customer-profile-grid dt { color: var(--admin-muted); font-size: 12px; font-weight: 400; }.customer-profile-grid dd { margin: 4px 0 0; font-size: 13px; overflow-wrap: anywhere; }.customer-notes { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 13px; margin: 0; }.customer-detail-section .customer-table td, .customer-detail-section .customer-table th { padding: 10px 8px; }.customer-detail-pagination { display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-top: 16px; }.customer-timeline { list-style: none; border-left: 2px solid var(--admin-border); padding-left: 20px; margin: 0 0 0 5px; }.customer-timeline li { position: relative; padding-bottom: 22px; }.customer-timeline li::before { content: ''; position: absolute; left: -25px; top: 5px; width: 8px; height: 8px; border-radius: 50%; background: var(--admin-primary); }.customer-timeline strong { display: block; font-size: 13px; font-weight: 500; }.customer-timeline small { display: block; color: var(--admin-muted); font-size: 11px; margin-top: 4px; }
.customer-mobile-sort { display: none; }.customer-workspace :is(button,input,select,textarea,a):focus-visible { outline: 3px solid var(--admin-primary); outline-offset: 3px; }.customer-workspace input[aria-invalid=true] { border-color: var(--admin-danger); }
@keyframes customer-shimmer { to { background-position: -200% 0; } }
@media (max-width: 1199px) { .customer-filters { grid-template-columns: repeat(2,minmax(0,1fr)); }.customer-search { grid-column: 1/-1; }.customer-row-actions { min-width: 110px; } }
@media (max-width: 767px) {
  .customer-heading { gap: 18px; margin-bottom: 24px; }.customer-heading h2 { font-size: 22px; }.customer-actions { width: 100%; }.customer-actions > * { flex: 1; }.customer-button { min-height: 40px; }.customer-panel-heading { padding: 18px 16px; gap: 12px; }.customer-filters { padding: 0 16px 20px; gap: 14px; }.customer-filters label:nth-child(5) { grid-column: auto; }.customer-panel-heading p { font-size: 12px; }.customer-bulk { padding: 16px; }.customer-bulk strong { flex-basis: 100%; }.customer-workspace .customer-bulk select { flex: 1; }.customer-mobile-sort { display: flex; align-items: flex-end; padding: 0 16px 16px; gap: 10px; }.customer-mobile-sort label { flex: 1; }
  .customer-table > thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }.customer-table, .customer-table > tbody { display: block; }.customer-row { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); padding: 18px 16px; gap: 16px; border-top: 1px solid var(--admin-border); }.customer-table .customer-row td { padding: 0; border: 0; min-width: 0; }.customer-selection { grid-column: 2; grid-row: 1; text-align: right; }.customer-profile-cell { grid-column: 1; grid-row: 1; }.customer-row td[data-label]:not(.customer-profile-cell)::before { display: block; content: attr(data-label); color: var(--admin-muted); font-size: 11px; margin-bottom: 4px; }.customer-row-actions { grid-column: 1/-1; display: flex; gap: 6px; }.customer-row-actions .customer-button { flex: 1; margin: 0; min-height: 36px; }.customer-profile { min-width: 0; }.customer-profile strong { overflow-wrap: anywhere; }.customer-avatar { width: 30px; height: 30px; }.customer-skeleton-row { display: flex; gap: 15px; padding: 20px 16px; }.customer-table .customer-skeleton-row td { padding: 0; flex: 1; }.customer-skeleton-row td:nth-child(n+4) { display: none; }.customer-pagination { padding: 16px; }.customer-pagination nav { width: 100%; justify-content: space-between; gap: 6px; }.customer-pagination .customer-button { padding-inline: 8px; }.customer-dialog-heading, .customer-dialog-body, .customer-dialog-footer { padding: 18px 16px; }.customer-form-grid, .customer-profile-grid { grid-template-columns: minmax(0,1fr); }.customer-drawer { border-radius: 0; }.customer-dialog-heading h2 { font-size: 19px; }.customer-detail-section .customer-table { display: table; }.customer-detail-section .customer-table > thead { position: static; width: auto; height: auto; overflow: visible; clip-path: none; }.customer-detail-section .customer-table > tbody { display: table-row-group; }.customer-detail-section .customer-table td { white-space: nowrap; }
}
@media (prefers-reduced-motion: reduce) { .customer-skeleton { animation: none; } }
</style>
