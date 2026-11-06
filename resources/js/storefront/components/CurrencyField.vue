<script setup>
/**
 * CurrencyField — configurable currency display field.
 *
 * Wraps SymbolSvg.vue and exposes controls for how a currency value is
 * displayed across the storefront:
 *   - Symbol style (svg / unicode / text)
 *   - Symbol position (before / after the amount)
 *   - Fallback text (default "AED")
 *   - Currency code (default "AED")
 *   - Decimal places (default 2)
 *
 * Designed to be used both in the admin content editor and directly in
 * storefront pages wherever currency formatting is needed.
 */

const props = defineProps({
    /** Current currency configuration object. */
    modelValue: {
        type: Object,
        default: () => ({
            currency: 'AED',
            symbolStyle: 'svg',
            symbolPosition: 'before',
            symbolText: 'AED',
            decimals: 2,
        }),
    },
    /** Label shown above the field group. */
    label: { type: String, default: 'Currency settings' },
    /** Additional CSS class for the wrapper. */
    class: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

function emitChange(field, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [field]: value,
    });
}

const symbolStyles = [
    { value: 'svg', label: 'Symbol SVG (UAE Dirham logo)' },
    { value: 'unicode', label: 'Unicode (د.إ)' },
    { value: 'text', label: 'Text code (AED)' },
];

const symbolPositions = [
    { value: 'before', label: 'Before amount (e.g. AED 100.00)' },
    { value: 'after', label: 'After amount (e.g. 100.00 AED)' },
];
</script>

<template>
    <div class="currency-field">
        <label class="currency-field__label">{{ props.label }}</label>

        <div class="currency-field__row">
            <div class="currency-field__control">
                <label class="currency-field__label-small">Currency code</label>
                <input
                    class="currency-field__input"
                    :value="props.modelValue.currency"
                    placeholder="AED"
                    @input="emitChange('currency', $event.target.value)"
                />
            </div>

            <div class="currency-field__control">
                <label class="currency-field__label-small">Symbol style</label>
                <select
                    class="currency-field__select"
                    :value="props.modelValue.symbolStyle"
                    @change="emitChange('symbolStyle', $event.target.value)"
                >
                    <option
                        v-for="style in symbolStyles"
                        :key="style.value"
                        :value="style.value"
                    >
                        {{ style.label }}
                    </option>
                </select>
            </div>

            <div class="currency-field__control">
                <label class="currency-field__label-small">Symbol position</label>
                <select
                    class="currency-field__select"
                    :value="props.modelValue.symbolPosition"
                    @change="emitChange('symbolPosition', $event.target.value)"
                >
                    <option
                        v-for="position in symbolPositions"
                        :key="position.value"
                        :value="position.value"
                    >
                        {{ position.label }}
                    </option>
                </select>
            </div>
        </div>

        <div class="currency-field__row mt-3">
            <div class="currency-field__control">
                <label class="currency-field__label-small">Fallback text</label>
                <input
                    class="currency-field__input"
                    :value="props.modelValue.symbolText"
                    placeholder="AED"
                    @input="emitChange('symbolText', $event.target.value)"
                />
            </div>

            <div class="currency-field__control">
                <label class="currency-field__label-small">Decimal places</label>
                <input
                    class="currency-field__input"
                    type="number"
                    min="0"
                    max="8"
                    :value="props.modelValue.decimals"
                    @input="emitChange('decimals', parseInt($event.target.value, 10) || 2)"
                />
            </div>
        </div>

        <!-- Live preview -->
        <div class="currency-field__preview mt-3">
            <label class="currency-field__label-small">Preview</label>
            <div class="currency-field__preview-value">
                <span class="currency-field__preview-label">
                    {{ props.modelValue.currency }}
                </span>
                <span v-if="props.modelValue.symbolStyle === 'svg'" class="currency-symbol">
                    <img
                        :src="'/storage/500px-UAE_Dirham_Symbol001.png'"
                        alt="AED"
                        class="currency-symbol__img"
                    />
                </span>
                <span
                    v-else-if="props.modelValue.symbolStyle === 'unicode'"
                    class="currency-symbol unicode"
                >
                    د.إ
                </span>
                <span v-else class="currency-symbol text">
                    {{ props.modelValue.symbolText }}
                </span>
                <span class="amount">
                    {{ Number(123.45).toFixed(props.modelValue.decimals || 2) }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.currency-field {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1rem;
    background: #f8fafc;
}

.currency-field__label {
    display: block;
    font-weight: 600;
    font-size: 0.875rem;
    color: #334155;
    margin-bottom: 0.75rem;
}

.currency-field__row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.currency-field__control {
    flex: 1;
    min-width: 180px;
    display: flex;
    flex-direction: column;
}

.currency-field__label-small {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.currency-field__input,
.currency-field__select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
    background: #ffffff;
    font-size: 0.875rem;
    color: #1e293b;
    box-sizing: border-box;
}

.currency-field__input:focus,
.currency-field__select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.currency-field__preview {
    border: 1px dashed #94a3b8;
    border-radius: 0.375rem;
    padding: 0.75rem;
    background: #ffffff;
}

.currency-field__preview-value {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #0f172a;
}

.currency-symbol {
    display: inline-flex;
    align-items: center;
}

.currency-symbol__img {
    width: 1.25rem;
    height: 1.25rem;
    vertical-align: middle;
}

.amount {
    color: #059669;
}
</style>