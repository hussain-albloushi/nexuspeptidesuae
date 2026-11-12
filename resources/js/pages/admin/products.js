import { computed, onScopeDispose, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { searchQuery } from './search.js';

export const productFieldGroups = [
    { title: 'Product information', fields: [
        { key: 'name', label: 'Product name', required: true, maxlength: 255 },
        { key: 'sku', label: 'SKU', required: true, maxlength: 255 },
        { key: 'slug', label: 'URL slug', required: true, maxlength: 255, pattern: '[a-z0-9]+(-[a-z0-9]+)*', hint: 'Lowercase letters, numbers, and hyphens.' },
        { key: 'category_id', label: 'Category', type: 'category' },
        { key: 'description', label: 'Description', type: 'textarea', maxlength: 10000, wide: true },
    ] },
    { title: 'Pricing & inventory', fields: [
        { key: 'price', label: 'Price (AED)', type: 'number', required: true, min: 0, max: 99999999.99, step: '0.01' },
        { key: 'compare_at_price', label: 'Compare-at price (AED)', type: 'number', min: 0, max: 99999999.99, step: '0.01' },
        { key: 'cost_price', label: 'Cost price (AED)', type: 'number', min: 0, max: 99999999.99, step: '0.01' },
        { key: 'stock_quantity', label: 'Stock quantity', type: 'number', required: true, min: 0, max: 1000000, step: '1' },
        { key: 'low_stock_threshold', label: 'Low-stock alert threshold', type: 'number', required: true, min: 0, max: 1000000, step: '1' },
    ] },
    { title: 'Publishing & search', fields: [
        { key: 'visibility', label: 'Visibility', type: 'visibility', required: true },
        { key: 'featured_image_url', label: 'Product image', type: 'image', maxlength: 255, wide: true, hint: 'JPG, PNG or WebP, up to 2 MB. Image changes take effect when you save.' },
        { key: 'seo_title', label: 'SEO title', maxlength: 255 },
        { key: 'seo_description', label: 'SEO description', type: 'textarea', maxlength: 10000, wide: true },
    ] },
];

export function productForm(product) {
    const form = Object.fromEntries(productFieldGroups.flatMap((group) => group.fields.map((field) => [field.key, product[field.key] ?? ''])));
    return { ...form, is_active: Boolean(product.is_active), featured: Boolean(product.featured), requires_subscription: Boolean(product.requires_subscription), expected_stock_quantity: product.stock_quantity };
}

export function stockStatus(product) {
    if (product.stock_quantity <= 0) return { key: 'out_of_stock', label: 'Out of stock' };
    if (product.stock_quantity <= product.low_stock_threshold) return { key: 'low_stock', label: 'Low stock' };
    return { key: 'in_stock', label: 'In stock' };
}

export function useProductImage() {
    const file = ref(null);
    const preview = ref('');
    const error = ref('');
    let objectUrl;
    function reset(url = '') {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = undefined;
        file.value = null;
        preview.value = url || '';
        error.value = '';
    }
    function select(selected) {
        if (!selected) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > 2 * 1024 * 1024) {
            error.value = 'Choose a JPG, PNG or WebP image up to 2 MB.';
            return;
        }
        reset();
        file.value = selected;
        objectUrl = URL.createObjectURL(selected);
        preview.value = objectUrl;
    }
    onScopeDispose(() => reset());
    return { file, preview, error, reset, select };
}

function failureMessage(failure, fallback) {
    if (failure.response?.status === 404) return 'This product is no longer available. Refresh the list to continue.';
    return fallback;
}

export function useProducts(route) {
    const filters = reactive(Object.fromEntries(['q', 'category_id', 'min_price', 'max_price', 'stock_status', 'is_active'].map((key) => [key, searchQuery(route.query[key])])));
    const products = ref([]);
    const categories = ref([]);
    const categoryError = ref('');
    const loading = ref(false);
    const error = ref('');
    const page = ref(1);
    const perPage = ref(20);
    const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
    const activeProduct = ref(null);
    const detailLoading = ref(false);
    const detailError = ref('');
    const busy = ref(false);
    const actionError = ref('');
    const fieldErrors = ref({});
    const success = ref('');
    const hasFilters = computed(() => Object.values(filters).some((value) => String(value).trim() !== ''));
    let timer;
    let listController;
    let detailController;
    let categoryController;
    let version = 0;
    let detailVersion = 0;
    let suppressPageWatch = false;
    let disposed = false;

    function cancelList() {
        clearTimeout(timer);
        listController?.abort();
        version++;
    }

    async function refresh() {
        if (disposed) return;
        cancelList();
        const current = version;
        listController = new AbortController();
        loading.value = true;
        error.value = '';
        products.value = [];
        try {
            const { data } = await axios.get('/api/products', {
                params: { ...Object.fromEntries(Object.entries(filters).filter(([, value]) => String(value).trim() !== '')), q: filters.q.trim(), page: page.value, per_page: perPage.value },
                signal: listController.signal,
            });
            if (current !== version || disposed) return;
            pagination.value = data;
            if (page.value > data.last_page) {
                suppressPageWatch = true;
                page.value = Math.max(1, data.last_page);
                suppressPageWatch = false;
                await refresh();
                return;
            }
            products.value = data.data;
        } catch (failure) {
            if (current === version && !axios.isCancel(failure)) {
                error.value = failure.response?.status === 422
                    ? Object.values(failure.response.data.errors || {}).flat().join(' ') || 'Check the search filters and try again.'
                    : 'Products could not be loaded. Please try again.';
            }
        } finally {
            if (current === version && !disposed) loading.value = false;
        }
    }

    async function loadCategories() {
        categoryController?.abort();
        categoryController = new AbortController();
        categoryError.value = '';
        try {
            const { data } = await axios.get('/api/categories', { signal: categoryController.signal });
            if (!disposed) categories.value = data;
        } catch (failure) {
            if (!axios.isCancel(failure) && !disposed) categoryError.value = 'Categories could not be loaded.';
        }
    }

    watch([() => JSON.stringify(filters), perPage, page], (values, previous) => {
        if (suppressPageWatch || disposed) return;
        if (previous && (values[0] !== previous[0] || values[1] !== previous[1])) {
            suppressPageWatch = true;
            page.value = 1;
            suppressPageWatch = false;
        }
        cancelList();
        products.value = [];
        loading.value = true;
        error.value = '';
        timer = setTimeout(refresh, 250);
    }, { flush: 'sync' });

    watch(() => route.query.q, (value) => { filters.q = searchQuery(value); });

    function resetFilters() {
        Object.keys(filters).forEach((key) => { filters[key] = ''; });
    }

    async function loadProduct(id) {
        detailController?.abort();
        detailController = new AbortController();
        const current = ++detailVersion;
        activeProduct.value = null;
        detailLoading.value = true;
        detailError.value = '';
        actionError.value = '';
        fieldErrors.value = {};
        try {
            const { data } = await axios.get('/api/products/' + id, { signal: detailController.signal });
            if (current === detailVersion && !disposed) activeProduct.value = data;
        } catch (failure) {
            if (current === detailVersion && !axios.isCancel(failure)) detailError.value = failureMessage(failure, 'Product details could not be loaded. Please retry.');
        } finally {
            if (current === detailVersion && !disposed) detailLoading.value = false;
        }
    }

    function closeProduct() {
        if (busy.value) return false;
        detailController?.abort();
        detailVersion++;
        activeProduct.value = null;
        detailLoading.value = false;
        actionError.value = '';
        fieldErrors.value = {};
        return true;
    }

    async function saveProduct(form, image = null) {
        if (busy.value || !activeProduct.value) return false;
        busy.value = true;
        actionError.value = '';
        fieldErrors.value = {};
        try {
            const payload = { ...form };
            for (const field of ['category_id', 'compare_at_price', 'cost_price', 'description', 'featured_image_url', 'seo_title', 'seo_description']) {
                if (payload[field] === '') payload[field] = null;
            }
            const endpoint = '/api/products/' + activeProduct.value.id;
            let response;
            if (image) {
                const body = new FormData();
                for (const [key, value] of Object.entries(payload)) body.append(key, value === null ? '' : typeof value === 'boolean' ? String(Number(value)) : String(value));
                body.append('image', image);
                body.append('_method', 'PUT');
                response = await axios.post(endpoint, body);
            } else {
                response = await axios.put(endpoint, payload);
            }
            const { data } = response;
            activeProduct.value = data.product;
            success.value = `“${data.product.name}” updated successfully.`;
            await refresh();
            return true;
        } catch (failure) {
            fieldErrors.value = failure.response?.data?.errors || {};
            actionError.value = failure.response?.status === 422 ? 'Please correct the highlighted fields.' : failureMessage(failure, 'Product could not be saved. Your changes are still here; please retry.');
            return false;
        } finally {
            busy.value = false;
        }
    }

    async function deleteProduct(confirmed = false) {
        if (confirmed !== true || busy.value || !activeProduct.value) return false;
        busy.value = true;
        actionError.value = '';
        try {
            const product = activeProduct.value;
            await axios.delete('/api/products/' + product.id);
            success.value = `“${product.name}” deleted successfully.`;
            activeProduct.value = null;
            await refresh();
            return true;
        } catch (failure) {
            actionError.value = failureMessage(failure, 'Product could not be deleted. Please try again.');
            return false;
        } finally {
            busy.value = false;
        }
    }

    onScopeDispose(() => {
        disposed = true;
        cancelList();
        detailController?.abort();
        categoryController?.abort();
        detailVersion++;
    });

    void refresh();
    void loadCategories();

    return { filters, products, categories, categoryError, loading, error, page, perPage, pagination, activeProduct, detailLoading, detailError, busy, actionError, fieldErrors, success, hasFilters, refresh, loadCategories, resetFilters, loadProduct, closeProduct, saveProduct, deleteProduct };
}
