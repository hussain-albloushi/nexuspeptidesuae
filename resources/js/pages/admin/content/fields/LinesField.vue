<script setup>
import { computed } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [Array, String], default: () => [] },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const text = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue.join('\n') : (props.modelValue ?? '')),
    set: (value) =>
        emit(
            'update:modelValue',
            value
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter(Boolean),
        ),
});
</script>

<template>
    <div>
        <label class="form-label fw-semibold mb-1">
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>
        <textarea
            v-model="text"
            class="form-control"
            :class="{ 'is-invalid': error }"
            :rows="field.rows || 4"
            :maxlength="field.max || undefined"
        ></textarea>
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="field.help" class="form-text">{{ field.help }}</div>
    </div>
</template>
