<script setup>
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label class="form-label fw-semibold mb-1">
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>
        <input
            class="form-control"
            :class="{ 'is-invalid': error }"
            :value="modelValue ?? ''"
            :placeholder="field.placeholder || ''"
            :maxlength="field.max || undefined"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="field.help" class="form-text">{{ field.help }}</div>
        <div v-if="field.max" class="form-text text-end">{{ String(modelValue ?? '').length }}/{{ field.max }}</div>
    </div>
</template>
