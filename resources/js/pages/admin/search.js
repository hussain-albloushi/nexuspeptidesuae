import { computed, onScopeDispose, ref, watch } from 'vue';
import axios from 'axios';

export function searchQuery(value) {
    return String((Array.isArray(value) ? value[0] : value) ?? '').trim();
}

/** Combined results across every searchable admin section, each deep-linked. */
export async function fetchResults(endpoint, query, signal) {
    const config = { params: { q: query }, signal };
    if (endpoint) {
        return (await axios.get(endpoint, config)).data;
    }

    const { data } = await axios.get('/api/global-search', config);

    // Section navigation links lead the list so "search anything" always lands somewhere.
    const sections = (data.sections ?? []).map((section) => ({
        id: `section:${section.path}`,
        name: `Open ${section.title}`,
        type: 'sections',
        status: '',
        detail: 'Admin section',
        link: { path: section.path },
    }));

    return [...sections, ...(data.results ?? [])];
}

/**
 * Search box for admin pages that load their whole collection at once:
 * hydrates from the route (so search deep links land filtered), follows
 * later route changes, and narrows rows client-side.
 */
export function useCollectionSearch(list, route, matchers) {
    const term = ref(searchQuery(route.query.q));

    watch(() => route.query.q, (value) => {
        term.value = searchQuery(value);
    });

    const filtered = computed(() => {
        const needle = term.value.trim().toLowerCase();
        if (!needle) return list.value;

        return list.value.filter((row) =>
            matchers.some((pick) => String(pick(row) ?? '').toLowerCase().includes(needle)),
        );
    });

    return { term, filtered };
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
            const records = await fetchResults(endpoint, query, controller.signal);
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

/**
 * Navbar quick search: debounced live results with no URL sync, so the box
 * always starts empty and never fights the page it is sitting on.
 */
export function useQuickSearch(debounceMs = 250) {
    const q = ref('');
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

    async function run() {
        cancel();
        const requestVersion = version;
        const query = searchQuery(q.value);
        activeQuery.value = query;
        results.value = [];
        error.value = '';
        loading.value = Boolean(query);

        if (!query) return;

        controller = new AbortController();
        try {
            const records = await fetchResults(null, query, controller.signal);
            if (requestVersion === version) results.value = records;
        } catch (failure) {
            if (requestVersion === version && !axios.isCancel(failure)) {
                error.value = 'Unable to load search results. Please try again.';
            }
        } finally {
            if (requestVersion === version) loading.value = false;
        }
    }

    watch(q, () => {
        cancel();
        timer = setTimeout(run, debounceMs);
    });

    onScopeDispose(cancel);

    return { q, results, loading, error, activeQuery, run };
}
