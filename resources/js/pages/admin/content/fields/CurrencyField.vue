<script setup>
import CurrencyField from '../../../../storefront/components/CurrencyField.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: Object, default: () => ({}) },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

function update(value) {
    emit('update:modelValue', value);
}
</script>

<template>
    <div>
        <label class="form-label fw-semibold mb-1">
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>
        <CurrencyField
            :model-value="modelValue"
            :field="field"
            @update:model-value="update"
        />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="field.help" class="form-text">{{ field.help }}</div>
    </div>
</template>
