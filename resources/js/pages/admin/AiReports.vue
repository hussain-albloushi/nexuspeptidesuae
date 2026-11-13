<template>
    <div class="ai-page">
        <div v-if="message" class="alert alert-success">{{ message }}</div>
        <div v-if="error" class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ error }}</span>
            <button class="btn btn-sm btn-outline-danger ms-auto" type="button" @click="load">Retry</button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <section class="card h-100">
                    <div class="card-header"><h3 class="card-title mb-0">Generate a report</h3></div>
                    <div class="card-body">
                        <p class="text-secondary small">
                            Reports are assembled from stored records. Numbers are computed, never AI-estimated. Open a report and use your
                            browser's print dialog to save it as PDF.
                        </p>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small text-uppercase text-secondary" for="report-type">Report type</label>
                                <select id="report-type" v-model="generateForm.type" class="form-select">
                                    <option v-for="type in types" :key="type.type" :value="type.type">{{ type.title }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-uppercase text-secondary" for="report-start">From (optional)</label>
                                <input id="report-start" v-model="generateForm.start" type="date" class="form-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-uppercase text-secondary" for="report-end">To (optional)</label>
                                <input id="report-end" v-model="generateForm.end" type="date" class="form-control" />
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="button" class="btn btn-primary" :disabled="generating" title="Generate report" @click="generate">
                                    <span v-if="generating" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="bi bi-magic"></i>
                                </button>
                            </div>
                        </div>
                        <small class="text-secondary d-block mt-2">Leave both dates blank to use the default window for the selected report type.</small>
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                <section class="card h-100">
                    <div class="card-header"><h3 class="card-title mb-0">Scheduled reports</h3></div>
                    <div class="card-body">
                        <p class="text-secondary small">Schedules run through the application's scheduler. Toggle a report type on or off.</p>
                        <div class="form-check form-switch mb-3" v-for="(label, key) in scheduleLabels" :key="key">
                            <input
                                :id="`schedule-${key}`"
                                class="form-check-input"
                                type="checkbox"
                                :checked="scheduled[key]"
                                :disabled="savingSchedule === key"
                                @change="toggleSchedule(key, $event.target.checked)"
                            />
                            <label class="form-check-label" :for="`schedule-${key}`">
                                {{ label }}
                                <span v-if="savingSchedule === key" class="spinner-border spinner-border-sm ms-1"></span>
                            </label>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <section class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0">Report library</h3>
                <select v-model="typeFilter" class="form-select form-select-sm ms-auto" style="max-width: 220px;" aria-label="Filter reports by type">
                    <option value="">All report types</option>
                    <option v-for="type in types" :key="type.type" :value="type.type">{{ type.title }}</option>
                </select>
            </div>
            <div v-if="loading" class="text-center py-5 text-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Loading reports…</div>
            <div v-else-if="!filteredReports.length" class="card-body text-center py-5">
                <i class="bi bi-file-earmark-text fs-1 text-secondary"></i>
                <h3 class="h5 mt-3">No reports yet</h3>
                <p class="text-secondary mb-0">Generate your first report using the form above.</p>
            </div>
            <div v-else class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Report</th><th>Period</th><th>Generated</th><th>Source</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="report in filteredReports" :key="report.id">
                            <td><div class="fw-semibold">{{ report.title }}</div><small class="text-secondary">{{ report.preview }}…</small></td>
                            <td class="small">{{ formatDate(report.period_start) }} → {{ formatDate(report.period_end) }}</td>
                            <td class="small">{{ formatDateTime(report.generated_at) }}</td>
                            <td><span class="badge" :class="report.scheduled ? 'text-bg-info' : 'text-bg-light'">{{ report.scheduled ? 'Scheduled' : 'Manual' }}</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="viewReport(report.id)"><i class="bi bi-eye"></i><span class="d-none d-md-inline ms-1">View</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="deleteReport(report)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="selected" class="ai-modal-backdrop" @click.self="selected = null">
            <div class="card ai-modal" role="dialog" aria-modal="true" aria-labelledby="report-title">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h3 id="report-title" class="card-title mb-0">{{ selected.title }}</h3>
                    <span class="badge text-bg-light">{{ formatDate(selected.period_start) }} → {{ formatDate(selected.period_end) }}</span>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="printReport"><i class="bi bi-printer me-1"></i>Print / PDF</button>
                        <button type="button" class="btn-close" aria-label="Close" @click="selected = null"></button>
                    </div>
                </div>
                <div class="card-body ai-report-body">
                    <AiMarkdown :content="selected.content" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import AiMarkdown from './AiMarkdown.vue';
import { formatDate, formatDateTime, renderMarkdown } from './ai.js';

const loading = ref(true);
const error = ref('');
const message = ref('');
const reports = ref([]);
const types = ref([]);
const scheduled = ref({});
const typeFilter = ref('');
const selected = ref(null);
const generating = ref(false);
const savingSchedule = ref(null);

const generateForm = ref({ type: 'weekly_report', start: '', end: '' });

const scheduleLabels = {
    daily_report: 'Daily business report',
    weekly_report: 'Weekly business report',
    monthly_report: 'Monthly business report',
};

const filteredReports = computed(() => (typeFilter.value
    ? reports.value.filter((report) => report.type === typeFilter.value)
    : reports.value));

function flash(text) {
    message.value = text;
    window.setTimeout(() => { message.value = ''; }, 5000);
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/ai/reports');
        reports.value = data?.reports || [];
        types.value = data?.types || [];
        scheduled.value = data?.scheduled || {};
        if (!generateForm.value.type && types.value.length) {
            generateForm.value.type = types.value[0].type;
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not load reports.';
    } finally {
        loading.value = false;
    }
}

async function generate() {
    generating.value = true;
    error.value = '';
    try {
        const payload = { type: generateForm.value.type };
        if (generateForm.value.start && generateForm.value.end) {
            payload.start = generateForm.value.start;
            payload.end = generateForm.value.end;
        }
        const { data } = await axios.post('/api/ai/reports/generate', payload);
        selected.value = data.report;
        flash('Report generated.');
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not generate the report.';
    } finally {
        generating.value = false;
    }
}

async function viewReport(id) {
    try {
        const { data } = await axios.get(`/api/ai/reports/${id}`);
        selected.value = data.report;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not open the report.';
    }
}

async function deleteReport(report) {
    if (!window.confirm(`Delete "${report.title}" for ${formatDate(report.period_start)} → ${formatDate(report.period_end)}?`)) return;
    try {
        await axios.delete(`/api/ai/reports/${report.id}`);
        reports.value = reports.value.filter((item) => item.id !== report.id);
        flash('Report deleted.');
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not delete the report.';
    }
}

async function toggleSchedule(key, enabled) {
    savingSchedule.value = key;
    error.value = '';
    try {
        await axios.post('/api/ai/reports/schedule', { report: key, enabled });
        scheduled.value = { ...scheduled.value, [key]: enabled };
        flash(`${scheduleLabels[key]} ${enabled ? 'enabled' : 'disabled'}.`);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Could not update the schedule.';
        await load();
    } finally {
        savingSchedule.value = null;
    }
}

function printReport() {
    if (!selected.value) return;
    const body = renderMarkdown(selected.value.content);
    const printWindow = window.open('', '_blank', 'width=900,height=1000');
    if (!printWindow) {
        error.value = 'Your browser blocked the print window. Allow pop-ups for this site and try again.';
        return;
    }
    printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${selected.value.title}</title>
        <style>
            body { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111; margin: 40px; line-height: 1.5; }
            h1 { font-size: 22px; } h2 { font-size: 17px; margin-top: 24px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
            h3 { font-size: 15px; } p, li, td, th { font-size: 13px; }
            table { border-collapse: collapse; width: 100%; margin: 12px 0; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
            th { background: #f3f5f6; }
            code { background: #f3f5f6; padding: 1px 4px; border-radius: 3px; }
            hr { border: 0; border-top: 1px solid #ddd; margin: 20px 0; }
            @media print { body { margin: 0; } }
        </style></head><body>${body}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

onMounted(load);
</script>

<style scoped>
.ai-page { max-width: 1300px; }
.ai-modal-backdrop { position: fixed; inset: 0; z-index: 1080; background: rgba(9, 14, 17, .55); display: flex; align-items: flex-start; justify-content: center; padding: 4vh 16px 16px; overflow-y: auto; }
.ai-modal { width: min(900px, 100%); box-shadow: 0 24px 60px rgba(0, 0, 0, .28); }
.ai-report-body { max-height: 74vh; overflow-y: auto; }
</style>
