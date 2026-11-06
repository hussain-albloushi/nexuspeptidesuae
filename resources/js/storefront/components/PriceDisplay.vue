<script setup>
import { computed } from 'vue';
import { useCurrencyStore } from '../stores/currency';

const props = defineProps({
    /** Numeric amount to display. */
    value: { type: [Number, String], required: true },
    /** CSS class for the amount text. */
    amountClass: { type: String, default: '' },
    /** CSS class for the currency symbol/image. */
    symbolClass: { type: String, default: 'h-[1.1em] w-auto inline-block align-baseline' },
});

const currency = useCurrencyStore();

const formattedAmount = computed(() => {
    return Number(props.value).toFixed(currency.settings.decimals);
});

const isSvg = computed(() => currency.settings.symbolStyle === 'svg');
const symbolPath = computed(() => currency.symbol);
const isAfter = computed(() => currency.settings.symbolPosition === 'after');
</script>

<template>
    <span class="inline-flex items-baseline gap-[0.25em]">
        <template v-if="!isAfter">
            <img v-if="isSvg" :src="symbolPath" alt="AED" :class="symbolClass" />
            <span v-else :class="symbolClass">{{ symbolPath }}</span>
        </template>

        <span :class="amountClass">{{ formattedAmount }}</span>

        <template v-if="isAfter">
            <img v-if="isSvg" :src="symbolPath" alt="AED" :class="symbolClass" />
            <span v-else :class="symbolClass">{{ symbolPath }}</span>
        </template>
    </span>
</template>
