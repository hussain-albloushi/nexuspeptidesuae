<template>
    <div class="card ai-kpi-card h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <span class="ai-kpi-label">{{ label }}</span>
                <span
                    v-if="deltaPercent !== null && deltaPercent !== undefined"
                    class="ai-kpi-trend"
                    :class="trendMeta(deltaPercent).class"
                >
                    <i class="bi" :class="trendMeta(deltaPercent).icon"></i>
                    {{ signedPercent(deltaPercent) }}
                </span>
            </div>
            <div class="ai-kpi-value">{{ kpiValue(value, unit, decimals) }}</div>
            <div v-if="previous !== null && previous !== undefined" class="ai-kpi-compare small text-secondary">
                vs {{ kpiValue(previous, unit, decimals) }}
                <span :class="trendMeta(deltaPercent).class">({{ kpiDelta(delta, unit, decimals) }})</span>
            </div>
            <p v-if="note" class="ai-kpi-note small text-secondary mb-0">{{ note }}</p>
        </div>
    </div>
</template>

<script setup>
import { kpiDelta, kpiValue, signedPercent, trendMeta } from './ai.js';

defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], default: null },
    previous: { type: [Number, String], default: null },
    delta: { type: [Number, String], default: null },
    deltaPercent: { type: [Number, String], default: null },
    unit: { type: String, default: '' },
    note: { type: String, default: '' },
    decimals: { type: Number, default: 2 },
});
</script>

<style scoped>
.ai-kpi-card .card-body { display: flex; flex-direction: column; gap: .35rem; }
.ai-kpi-label { font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--admin-muted); }
.ai-kpi-value { font-size: 1.35rem; font-weight: 700; letter-spacing: -.02em; color: var(--admin-text); }
.ai-kpi-trend { font-size: .78rem; font-weight: 600; white-space: nowrap; }
.ai-kpi-compare { font-size: .75rem; }
.ai-kpi-note { margin-top: auto !important; line-height: 1.45; }
</style>
