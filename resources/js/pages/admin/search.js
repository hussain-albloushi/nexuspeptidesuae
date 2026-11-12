import { onScopeDispose, ref, watch } from 'vue';
import axios from 'axios';

export function searchQuery(value) {
    return String((Array.isArray(value) ? value[0] : value) ?? '').trim();
}

async function loadResults(endpoint, query, signal) {
    const config = { params: { q: query }, signal };
    if (endpoint) {
        const { data } = await axios.get(endpoint, config);
        return data;
    }

    const [orders, products, customers] = await Promise.all([
        axios.get('/api/orders', config),
        axios.get('/api/products', config),
        axios.get('/api/customers', config),
    ]);

    return [
        ...orders.data.map((order) => ({
            id: order.id,
            name: String(order.order_number || order.id),
            type: 'orders',
            status: order.status || '',
            detail: `Customer: ${order.user?.name || '—'} · AED ${order.total_amount ?? 0}`,
        })),
        ...products.data.map((product) => ({
            id: product.id,
            name: product.name,
            type: 'products',
            status: product.is_active ? 'Active' : 'Draft',
            detail: `SKU: ${product.sku || '—'} · Stock: ${product.stock_quantity ?? 0} · AED ${product.price ?? 0}`,
        })),
        ...customers.data.map((customer) => ({
            id: customer.id,
            name: customer.name,
            type: 'customers',
            status: '',
            detail: customer.email || '—',
        })),
    ];
}

export function useAdminSearch(route, router, endpoint = null, debounceMs = 250) {
    const q = ref(searchQuery(route.query.q));
    const results = ref([]);
    const loading = ref(false);
    const error = ref('');
    const activeQuery = ref('');
    let timer;
    let controller;
    let version = 0;

    function cancel() {
        clearTimeout(timer);
        controller?.abort();
        version++;
    }

    async function runSearch() {
        cancel();
        const requestVersion = version;
        const query = searchQuery(q.value);
        activeQuery.value = query;
        results.value = [];
        error.value = '';
        loading.value = false;
        if (!query && !endpoint) return;

        controller = new AbortController();
        loading.value = true;
        try {
            const records = await loadResults(endpoint, query, controller.signal);
            if (requestVersion === version) results.value = records;
        } catch (failure) {
            if (requestVersion === version && !axios.isCancel(failure)) {
                error.value = 'Unable to load search results. Please try again.';
            }
        } finally {
            if (requestVersion === version) loading.value = false;
        }
    }

    watch(q, (value, previous) => {
        cancel();
        results.value = [];
        error.value = '';
        activeQuery.value = searchQuery(value);
        loading.value = Boolean(activeQuery.value || endpoint);
        if (previous === undefined || !activeQuery.value) {
            void runSearch();
        } else {
            timer = setTimeout(runSearch, debounceMs);
        }
    }, { immediate: true, flush: 'sync' });

    watch(() => route.query.q, (value) => {
        q.value = searchQuery(value);
    }, { flush: 'sync' });

    async function search() {
        const query = searchQuery(q.value);
        await router.replace({ query: { ...route.query, q: query || undefined } });
        await runSearch();
    }

    onScopeDispose(cancel);

    return { q, results, loading, error, activeQuery, search };
}
