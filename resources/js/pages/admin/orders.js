import { computed, onScopeDispose, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { searchQuery } from './search.js';

export const orderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
export const paymentStates = ['pending', 'unpaid', 'paid', 'partially_paid', 'failed', 'refunded', 'partially_refunded'];
export const orderColumns = [
    { key: 'order_number', label: 'Order ID' }, { key: 'customer', label: 'Customer' },
    { key: 'created_at', label: 'Date' }, { key: 'status', label: 'Status' },
    { key: 'payment_status', label: 'Payment' }, { key: 'total_amount', label: 'Total' },
];
export const orderLabel = (value) => String(value || 'not recorded').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
export const paymentMethodLabel = (value) => ({
    cod: 'COD (Cash On Delivery)',
    stripe: 'Credit / debit card (Stripe)',
}[value] ?? orderLabel(value));
export const orderMoney = (value) => new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED' }).format(Number(value || 0));

export function addressFields(value) {
    if (!value) return [];
    let address = value;
    if (typeof address === 'string') {
        try { address = JSON.parse(address); } catch { return [{ label: 'Address', value: address }]; }
    }
    if (!address || typeof address !== 'object') return [{ label: 'Address', value: String(address || '') }];
    return Object.entries(address).filter(([, entry]) => entry !== null && entry !== '').map(([key, entry]) => ({ label: orderLabel(key), value: typeof entry === 'object' ? JSON.stringify(entry) : String(entry) }));
}

function errorMessage(failure, fallback) {
    if (failure.response?.status === 422) return Object.values(failure.response.data.errors || {}).flat().join(' ') || failure.response.data.message || fallback;
    if (failure.response?.status === 404) return 'This order is no longer available. Refresh the list to continue.';
    return fallback;
}

export function useOrders(route) {
    const filters = reactive(Object.fromEntries(['q', 'status', 'payment_status', 'start_date', 'end_date'].map((key) => [key, searchQuery(route.query[key])])));
    const orders = ref([]);
    const loading = ref(false);
    const error = ref('');
    const success = ref('');
    const page = ref(1);
    const perPage = ref(20);
    const sort = ref('created_at');
    const direction = ref('desc');
    const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
    const selectedIds = ref([]);
    const selected = computed(() => orders.value.filter((order) => selectedIds.value.includes(order.id)));
    const allSelected = computed(() => orders.value.length > 0 && selected.value.length === orders.value.length);
    const commonTransitions = computed(() => selected.value.length ? (selected.value[0].allowed_transitions || []).filter((status) => selected.value.every((order) => order.allowed_transitions?.includes(status))) : []);
    const hasFilters = computed(() => Object.values(filters).some((value) => String(value).trim() !== ''));
    const detail = ref(null);
    const detailLoading = ref(false);
    const detailError = ref('');
    const busy = ref(false);
    const actionError = ref('');
    const exportBusy = ref(false);
    const exportError = ref('');
    let listController;
    let detailController;
    let timer;
    let version = 0;
    let detailVersion = 0;
    let suppress = false;
    let disposed = false;

    function params() {
        return { ...Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value).trim() !== '')), sort: sort.value, direction: direction.value };
    }
    function cancelList() { clearTimeout(timer); listController?.abort(); version++; }
    async function refresh() {
        if (disposed) return;
        cancelList();
        const current = version;
        listController = new AbortController();
        loading.value = true;
        error.value = '';
        orders.value = [];
        selectedIds.value = [];
        try {
            const { data } = await axios.get('/api/orders', { params: { ...params(), page: page.value, per_page: perPage.value }, signal: listController.signal });
            if (current !== version || disposed) return;
            pagination.value = data;
            if (page.value > data.last_page) {
                suppress = true;
                page.value = Math.max(1, data.last_page);
                suppress = false;
                await refresh();
                return;
            }
            orders.value = data.data;
        } catch (failure) {
            if (current === version && !axios.isCancel(failure)) error.value = errorMessage(failure, 'Orders could not be loaded. Please try again.');
        } finally {
            if (current === version && !disposed) loading.value = false;
        }
    }
    watch([() => JSON.stringify(filters), perPage, sort, direction, page], (current, previous) => {
        if (suppress || disposed) return;
        if (current.slice(0, 4).some((value, index) => value !== previous[index])) {
            suppress = true; page.value = 1; suppress = false;
        }
        cancelList();
        orders.value = []; selectedIds.value = []; loading.value = true; error.value = '';
        timer = setTimeout(refresh, 250);
    }, { flush: 'sync' });
    watch(() => route.query.q, (value) => { filters.q = searchQuery(value); });

    function resetFilters() { Object.keys(filters).forEach((key) => { filters[key] = ''; }); }
    function sortBy(column) {
        if (sort.value === column) direction.value = direction.value === 'asc' ? 'desc' : 'asc';
        else { sort.value = column; direction.value = 'asc'; }
    }
    function toggleSelection(id) { selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter((selectedId) => selectedId !== id) : [...selectedIds.value, id]; }
    function toggleAll() { selectedIds.value = allSelected.value ? [] : orders.value.map((order) => order.id); }

    async function loadOrder(id) {
        detailController?.abort();
        detailController = new AbortController();
        const current = ++detailVersion;
        detail.value = null; detailLoading.value = true; detailError.value = ''; actionError.value = '';
        try {
            const { data } = await axios.get('/api/orders/' + id, { signal: detailController.signal });
            if (current === detailVersion && !disposed) detail.value = data;
        } catch (failure) {
            if (current === detailVersion && !axios.isCancel(failure)) detailError.value = errorMessage(failure, 'Order details could not be loaded. Please retry.');
        } finally { if (current === detailVersion && !disposed) detailLoading.value = false; }
    }
    function closeOrder() {
        if (busy.value) return false;
        detailController?.abort(); detailVersion++; detail.value = null; detailLoading.value = false;
        return true;
    }
    async function updateStatuses(targets, status, notes = '', tracking = '', confirmed = false) {
        if (busy.value || !targets.length || (status === 'cancelled' && confirmed !== true)) return false;
        busy.value = true; actionError.value = ''; success.value = '';
        const requested = targets.map((order) => ({ id: order.id, expected_status: order.status }));
        try {
            const response = targets.length === 1
                ? await axios.patch('/api/orders/' + targets[0].id + '/status', { status, expected_status: targets[0].status, notes, ...(status === 'shipped' ? { tracking_number: tracking || null } : {}) })
                : await axios.post('/api/orders/bulk-status', { status, orders: requested, notes });
            if (detail.value && targets.some((order) => order.id === detail.value.id)) {
                if (response.data.order) detail.value = response.data.order;
                else await loadOrder(detail.value.id);
            }
            success.value = response.data.message;
            await refresh();
            return true;
        } catch (failure) {
            actionError.value = errorMessage(failure, 'Order status could not be updated. Please try again.');
            return false;
        } finally { busy.value = false; }
    }

    async function collectPayment(order) {
        if (busy.value) return false;
        busy.value = true; actionError.value = ''; success.value = '';
        try {
            const { data } = await axios.patch('/api/orders/' + order.id + '/payment', { confirmed: true, expected_payment_status: order.payment_status });
            if (detail.value?.id === order.id) detail.value = data.order;
            success.value = data.message;
            await refresh();
            return true;
        } catch (failure) {
            actionError.value = errorMessage(failure, 'Payment could not be recorded. Please refresh and try again.');
            return false;
        } finally { busy.value = false; }
    }

    /** Record a refund that was performed with the payment provider. */
    async function recordRefund(order, amount, notes) {
        if (busy.value) return false;
        busy.value = true; actionError.value = ''; success.value = '';
        try {
            const { data } = await axios.patch('/api/orders/' + order.id + '/refund', { amount, notes });
            if (detail.value?.id === order.id) detail.value = data.order;
            success.value = data.message;
            await refresh();
            return true;
        } catch (failure) {
            actionError.value = errorMessage(failure, 'The refund could not be recorded. Please refresh and try again.');
            return false;
        } finally { busy.value = false; }
    }

    /** Approve or reject a customer return request on an order. */
    async function respondReturn(order, returnId, decision, payload = {}) {
        if (busy.value) return false;
        busy.value = true; actionError.value = ''; success.value = '';
        try {
            const { data } = await axios.patch('/api/orders/' + order.id + '/returns/' + returnId, { decision, ...payload });
            if (detail.value?.id === order.id) detail.value = data.order;
            success.value = data.message;
            await refresh();
            return true;
        } catch (failure) {
            actionError.value = errorMessage(failure, 'The return request could not be updated. Please refresh and try again.');
            return false;
        } finally { busy.value = false; }
    }

    async function exportOrders(format = 'csv', selectedOnly = false) {
        if (exportBusy.value || (selectedOnly && !selected.value.length)) return false;
        exportBusy.value = true; exportError.value = '';
        let printWindow;
        try {
            if (format === 'print') {
                printWindow = window.open('', '_blank');
                if (!printWindow) throw new Error('popup');
                printWindow.opener = null;
                printWindow.document.write('<p>Preparing orders for printing…</p>');
            }
            const exportParams = selectedOnly ? { ids: selected.value.map((order) => order.id), sort: sort.value, direction: direction.value } : params();
            const { data } = await axios.get('/api/orders/export', { params: { ...exportParams, format }, responseType: format === 'csv' ? 'blob' : 'text' });
            if (format === 'print') {
                printWindow.document.open(); printWindow.document.write(data); printWindow.document.close();
            } else {
                const url = URL.createObjectURL(data);
                const link = document.createElement('a');
                link.href = url; link.download = 'orders-' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.append(link); link.click(); link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            }
            return true;
        } catch (failure) {
            printWindow?.close();
            exportError.value = failure.message === 'popup' ? 'Allow pop-ups to open the print / PDF view, then try again.' : 'Orders could not be exported. Please retry.';
            return false;
        } finally { exportBusy.value = false; }
    }
    async function printInvoice(order) {
        if (exportBusy.value) return false;
        exportBusy.value = true; exportError.value = '';
        let printWindow;
        try {
            printWindow = window.open('', '_blank');
            if (!printWindow) throw new Error('popup');
            printWindow.opener = null;
            printWindow.document.write('<p>Preparing invoice…</p>');
            const { data } = await axios.get('/api/orders/' + order.id + '/invoice', { responseType: 'text' });
            printWindow.document.open(); printWindow.document.write(data); printWindow.document.close();
            return true;
        } catch (failure) {
            printWindow?.close();
            exportError.value = failure.message === 'popup' ? 'Allow pop-ups to open the invoice, then try again.' : 'The invoice could not be opened. Please retry.';
            return false;
        } finally { exportBusy.value = false; }
    }
    onScopeDispose(() => { disposed = true; cancelList(); detailController?.abort(); detailVersion++; });
    void refresh();
    return { filters, orders, loading, error, success, page, perPage, sort, direction, pagination, selectedIds, selected, allSelected, commonTransitions, hasFilters, detail, detailLoading, detailError, busy, actionError, exportBusy, exportError, refresh, resetFilters, sortBy, toggleSelection, toggleAll, loadOrder, closeOrder, updateStatuses, collectPayment, recordRefund, respondReturn, exportOrders, printInvoice };
}
