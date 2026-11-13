<template>
    <div class="card ai-period-card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div class="ai-period-presets" role="group" aria-label="Analysis period">
                <button
                    v-for="preset in periods"
                    :key="preset.value"
                    type="button"
                    class="btn btn-sm"
                    :class="period === preset.value ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="pick(preset.value)"
                >{{ preset.label }}</button>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <label class="small text-secondary mb-0" for="ai-period-start">From</label>
                <input id="ai-period-start" v-model="customStart" type="date" class="form-control form-control-sm" :class="{ 'is-invalid': period === 'custom' && (!customStart || !customEnd) }" />
                <label class="small text-secondary mb-0" for="ai-period-end">To</label>
                <input id="ai-period-end" v-model="customEnd" type="date" class="form-control form-control-sm" :class="{ 'is-invalid': period === 'custom' && (!customStart || !customEnd) }" />
                <button type="button" class="btn btn-sm btn-outline-primary" @click="applyCustom">Apply</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" title="Refresh" @click="$emit('refresh')">
                    <i class="bi bi-arrow-clockwise"></i><span class="d-none d-md-inline ms-1">Refresh</span>
                </button>
            </div>
        </div>
        <div v-if="periodLabel || generatedAt" class="card-footer py-2 small text-secondary d-flex flex-wrap justify-content-between gap-2">
            <span v-if="periodLabel"><i class="bi bi-calendar3 me-1"></i>{{ periodLabel }}</span>
            <span v-if="generatedAt"><i class="bi bi-clock-history me-1"></i>Analysis generated {{ formattedGeneratedAt }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { AI_PERIODS, formatDateTime } from './ai.js';

const props = defineProps({
    period: { type: String, default: '30d' },
    start: { type: String, default: '' },
    end: { type: String, default: '' },
    periodLabel: { type: String, default: '' },
    generatedAt: { type: String, default: '' },
});
const emit = defineEmits(['update:period', 'update:start', 'update:end', 'refresh']);

const periods = AI_PERIODS;
const customStart = ref(props.start);
const customEnd = ref(props.end);

watch(() => props.start, (value) => { customStart.value = value; });
watch(() => props.end, (value) => { customEnd.value = value; });

const formattedGeneratedAt = computed(() => formatDateTime(props.generatedAt));

function pick(value) {
    emit('update:period', value);
    emit('update:start', '');
    emit('update:end', '');
}

function applyCustom() {
    if (!customStart.value || !customEnd.value) return;
    emit('update:start', customStart.value);
    emit('update:end', customEnd.value);
    emit('update:period', 'custom');
}
</script>

<style scoped>
.ai-period-card { border: 1px solid var(--admin-border); }
.ai-period-presets { display: flex; flex-wrap: wrap; gap: .25rem; }
.ai-period-presets .btn { font-size: .78rem; }
</style>
