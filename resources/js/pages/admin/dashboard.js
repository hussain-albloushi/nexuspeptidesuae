import { onScopeDispose, ref, watch } from 'vue';
import axios from 'axios';

export function useDashboard(endpoint = '/api/dashboard') {
    const days = ref(30);
    const data = ref(null);
    const loading = ref(false);
    const error = ref('');
    const autoRefresh = ref(true);
    let timer;
    let controller;
    let version = 0;

    async function refresh() {
        controller?.abort();
        controller = new AbortController();
        const requestVersion = ++version;
        loading.value = true;
        error.value = '';
        try {
            const response = await axios.get(endpoint, { params: { days: days.value }, signal: controller.signal });
            if (requestVersion === version) data.value = response.data;
        } catch (failure) {
            if (requestVersion === version && !axios.isCancel(failure)) {
                error.value = 'Dashboard could not be updated. Please retry.';
            }
        } finally {
            if (requestVersion === version) loading.value = false;
        }
    }

    function refreshIfVisible() {
        if (autoRefresh.value && !document.hidden && !loading.value) void refresh();
    }

    function start() {
        if (timer !== undefined) return;
        void refresh();
        timer = setInterval(refreshIfVisible, 30000);
        document.addEventListener('visibilitychange', refreshIfVisible);
    }

    function stop() {
        clearInterval(timer);
        timer = undefined;
        controller?.abort();
        version++;
        document.removeEventListener('visibilitychange', refreshIfVisible);
    }

    watch(days, () => { void refresh(); });
    onScopeDispose(stop);

    return { days, data, loading, error, autoRefresh, refresh, start };
}
