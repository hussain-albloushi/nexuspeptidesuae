<template>
    <div class="ai-page">
        <AiPeriodBar
            v-model:period="period" v-model:start="start" v-model:end="end"
            :period-label="periodLabel"
            @refresh="load"
        />
        <p class="small text-secondary mt-0 mb-3">
            <i class="bi bi-info-circle me-1"></i>The analysis window applies when regenerating recommendations. The list below covers all stored recommendations.
        </p>

        <div v-if="message" class="alert" :class="messageType === 'error' ? 'alert-danger' : 'alert-success'">
            {{ message }}
        </div>
        <div v-if="error" class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ error }}</span>
            <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="load">Retry</button>
        </div>

        <section class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <div class="ai-status-counts">
                    <span v-for="status in statuses" :key="status" class="ai-status-chip">
                        <span class="badge" :class="statusBadges[status] || 'text-bg-secondary'">{{ titleCase(status) }}</span>
                        <strong>{{ number(counts[status] || 0) }}</strong>
                    </span>
                </div>
                <button type="button" class="btn btn-primary ms-auto" :disabled="regenerating" @click="regenerate">
                    <span v-if="regenerating" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-arrow-repeat me-1"></i>
                    {{ regenerating ? 'Analysing…' : 'Generate recommendations' }}
                </button>
            </div>
        </section>

        <section class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input v-model="search" type="search" class="form-control" placeholder="Search recommendations" aria-label="Search recommendations" />
                    </div>
                    <select v-model="filters.category" class="form-select" style="max-width: 240px;" aria-label="Filter by category">
                        <option value="">All categories</option>
                        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
                    </select>
                    <select v-model="filters.priority" class="form-select" style="max-width: 170px;" aria-label="Filter by priority">
                        <option value="">All priorities</option>
                        <option v-for="priority in priorities" :key="priority" :value="priority">{{ titleCase(priority) }}</option>
                    </select>
                    <select v-model="filters.status" class="form-select" style="max-width: 170px;" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ titleCase(status) }}</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" @click="resetFilters"><i class="bi bi-x-circle me-1"></i>Clear</button>
                </div>
            </div>
        </section>

        <div v-if="loading && !data.length" class="text-center py-5 text-secondary">
            <span class="spinner-border spinner-border-sm me-2"></span>Loading recommendations…
        </div>

        <div v-else-if="!data.length" class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-stars fs-1 text-secondary"></i>
                <h3 class="h5 mt-3">No recommendations yet</h3>
                <p class="text-secondary mb-3">Generate recommendations to surface revenue, margin and inventory opportunities from your stored data.</p>
                <button type="button" class="btn btn-primary" :disabled="regenerating" @click="regenerate">Generate recommendations</button>
            </div>
        </div>

        <div v-else class="d-flex flex-column gap-3">
            <article v-for="item in data" :key="item.id" class="card ai-recommendation">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge" :class="priorityBadges[item.priority] || 'text-bg-secondary'">{{ titleCase(item.priority) }}</span>
                        <span class="badge text-bg-light">{{ item.category }}</span>
                        <span class="badge" :class="statusBadges[item.status] || 'text-bg-secondary'">{{ titleCase(item.status) }}</span>
                        <small class="text-secondary ms-auto">{{ formatDateTime(item.created_at) }}</small>
                    </div>
                    <h3 class="h5 mb-2">{{ item.title }}</h3>
                    <p class="mb-2">{{ item.description }}</p>
                    <p v-if="item.reasoning" class="small text-secondary mb-2"><i class="bi bi-lightbulb me-1"></i>{{ item.reasoning }}</p>

                    <div v-if="item.suggested_action" class="ai-action-callout mb-2">
                        <strong>Suggested action:</strong> {{ item.suggested_action }}
                    </div>
                    <p v-if="item.expected_impact" class="small mb-2"><strong>Expected impact:</strong> {{ item.expected_impact }}</p>

                    <div v-if="supportingEntries(item).length" class="d-flex flex-wrap gap-2 mb-2">
                        <span v-for="entry in supportingEntries(item)" :key="entry.key" class="badge text-bg-light">
                            {{ titleCase(entry.key) }}: {{ entry.value }}
                        </span>
                    </div>

                    <div v-if="item.relevant_products?.length || item.relevant_categories?.length" class="small text-secondary mb-2">
                        <span v-if="item.relevant_products?.length"><i class="bi bi-box me-1"></i>{{ item.relevant_products.length }} relevant product(s)</span>
                        <span v-if="item.relevant_categories?.length" class="ms-3"><i class="bi bi-tags me-1"></i>{{ item.relevant_categories.join(', ') }}</span>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 border-top pt-3 mt-2">
                        <label class="small text-secondary mb-0" :for="`status-${item.id}`">Status</label>
                        <select
                            :id="`status-${item.id}`"
                            class="form-select form-select-sm"
                            style="max-width: 180px;"
                            :value="item.status"
                            :disabled="savingStatus === item.id"
                            @change="updateStatus(item, $event.target.value)"
                        >
                            <option v-for="status in statuses" :key="status" :value="status">{{ titleCase(status) }}</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggleNotes(item)">
                            <i class="bi bi-journal-text me-1"></i>{{ openNotes === item.id ? 'Hide notes' : 'Internal notes' }}
                        </button>
                        <span v-if="item.implemented_at" class="small text-success ms-auto"><i class="bi bi-check2-circle me-1"></i>Implemented {{ formatDateTime(item.implemented_at) }}</span>
                    </div>

                    <div v-if="openNotes === item.id" class="mt-3">
                        <label class="form-label small text-uppercase text-secondary" :for="`note-${item.id}`">Internal notes</label>
                        <textarea :id="`note-${item.id}`" v-model="noteDraft" class="form-control" rows="3" maxlength="5000" placeholder="Why did you accept or dismiss this? Visible to admins only."></textarea>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-primary" :disabled="savingNote" @click="saveNote(item)">
                                <span v-if="savingNote" class="spinner-border spinner-border-sm me-1"></span>Save note
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="openNotes = null">Cancel</button>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="data.length" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <small class="text-secondary">Showing {{ data.length }} of {{ number(meta.total) }}</small>
            <div class="btn-group" role="group" aria-label="Pagination">
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.page <= 1" @click="goPage(meta.page - 1)">Previous</button>
                <button type="button" class="btn btn-sm btn-outline-secondary disabled">Page {{ meta.page }} of {{ totalPages }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.page >= totalPages" @click="goPage(meta.page + 1)">Next</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AiPeriodBar from './AiPeriodBar.vue';
import {
    AI_PERIODS, PRIORITY_BADGES, STATUS_BADGES, formatDate, formatDateTime, number,
    periodQuery, titleCase,
} from './ai.js';

const period = ref('30d');
const start = ref('');
const end = ref('');

const loading = ref(true);
const error = ref('');
const message = ref('');
const messageType = ref('success');
const data = ref([]);
const meta = ref({ page: 1, per_page: 15, total: 0 });
const counts = ref({});
const categories = ref([]);
const priorities = ref(['critical', 'high', 'medium', 'low']);
const statuses = ref(['new', 'reviewed', 'in_progress', 'completed', 'dismissed']);
const filters = ref({ category: '', priority: '', status: '' });
const search = ref('');
const regenerating = ref(false);
const savingStatus = ref(null);
const savingNote = ref(false);
const openNotes = ref(null);
const noteDraft = ref('');

const priorityBadges = PRIORITY_BADGES;
const statusBadges = STATUS_BADGES;

let requestId = 0;
let searchTimer = null;

const periodLabel = computed(() => {
    if (period.value === 'custom' && start.value && end.value) {
        return `${formatDate(start.value)} → ${formatDate(end.value)}`;
    }

    const preset = AI_PERIODS.find((item) => item.value === period.value);

    return preset ? preset.label : 'Selected period';
});

const totalPages = computed(() => Math.max(1, Math.ceil((meta.value.total || 0) / (meta.value.per_page || 15))));

function supportingEntries(item) {
    const supporting = item.supporting_data;
    if (!supporting || typeof supporting !== 'object') return [];

    return Object.entries(supporting)
        .filter(([, value]) => value === null || ['string', 'number', 'boolean'].includes(typeof value))
        .slice(0, 6)
        .map(([key, value]) => ({ key, value: typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value) }));
}

function flash(text, type = 'success') {
    message.value = text;
    messageType.value = type;
    window.setTimeout(() => { message.value = ''; }, 5000);
}

async function load() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams({ page: String(meta.value.page), per_page: String(meta.value.per_page) });
    if (search.value.trim()) params.set('search', search.value.trim());
    Object.entries(filters.value).forEach(([key, value]) => { if (value) params.set(key, value); });

    try {
        const { data: response } = await axios.get(`/api/ai/recommendations?${params.toString()}`);
        if (id !== requestId) return;
        data.value = response?.data || [];
        meta.value = response?.meta || { page: 1, per_page: 15, total: 0 };
        counts.value = response?.counts || {};
        categories.value = response?.categories || [];
        priorities.value = response?.priorities || priorities.value;
        statuses.value = response?.statuses || statuses.value;
    } catch (e) {
        if (id !== requestId) return;
        error.value = e?.response?.data?.message || 'Could not load recommendations.';
    } finally {
        if (id === requestId) loading.value = false;
    }
}

function resetFilters() {
    search.value = '';
    filters.value = { category: '', priority: '', status: '' };
    meta.value.page = 1;
    load();
}

function goPage(page) {
    if (page < 1 || page > totalPages.value) return;
    meta.value.page = page;
    load();
}

async function regenerate() {
    regenerating.value = true;
    error.value = '';
    try {
        const { data: response } = await axios.post('/api/ai/recommendations/regenerate', null, {
            params: Object.fromEntries(new URLSearchParams(periodQuery(period.value, start.value, end.value))),
        });
        flash(response?.message || 'Recommendations updated.');
        meta.value.page = 1;
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not generate recommendations.';
    } finally {
        regenerating.value = false;
    }
}

async function updateStatus(item, status) {
    savingStatus.value = item.id;
    error.value = '';
    try {
        const { data: response } = await axios.put(`/api/ai/recommendations/${item.id}/status`, { status });
        Object.assign(item, response.recommendation);
        counts.value = { ...counts.value };
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not update the status.';
        await load();
    } finally {
        savingStatus.value = null;
    }
}

function toggleNotes(item) {
    openNotes.value = openNotes.value === item.id ? null : item.id;
    noteDraft.value = item.internal_notes || '';
}

async function saveNote(item) {
    savingNote.value = true;
    error.value = '';
    try {
        const { data: response } = await axios.put(`/api/ai/recommendations/${item.id}/note`, { internal_notes: noteDraft.value });
        Object.assign(item, response.recommendation);
        openNotes.value = null;
        flash('Internal note saved.');
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not save the note.';
    } finally {
        savingNote.value = false;
    }
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { meta.value.page = 1; load(); }, 350);
});
watch(filters, () => { meta.value.page = 1; load(); }, { deep: true });

onMounted(load);
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<style scoped>
.ai-page { max-width: 1200px; }
.ai-status-counts { display: flex; flex-wrap: wrap; gap: 1rem; }
.ai-status-chip { display: inline-flex; align-items: center; gap: .4rem; }
.ai-status-chip strong { font-size: .95rem; }
.ai-recommendation { border-left: 3px solid var(--admin-border); }
.ai-action-callout { background: var(--admin-inset); border-radius: 8px; padding: .65rem .85rem; font-size: .875rem; }
</style>
