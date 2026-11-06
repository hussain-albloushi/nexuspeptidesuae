<script setup>
defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
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
        <select
            class="form-select"
            :class="{ 'is-invalid': error }"
            :value="modelValue ?? ''"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-for="option in field.options || []" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="field.help" class="form-text">{{ field.help }}</div>
    </div>
</template>
