<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [Array, String], default: () => [] },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

function toText(value) {
    return Array.isArray(value) ? value.join('\n') : (value ?? '');
}

// Local, un-normalized copy of the textarea contents. The value must NOT be
// trimmed/filtered while typing: doing so rewrites the input on every
// keystroke and swallows the space you just typed (words run together) and
// any blank line you press Enter to create.
const text = ref(toText(props.modelValue));

// Keep in sync when the parent replaces the value externally (switching
// sections, reloading after save).
watch(
    () => props.modelValue,
    (value) => {
        const next = toText(value);
        if (next !== text.value) {
            text.value = next;
        }
    },
);

function onInput(event) {
    text.value = event.target.value;
    emit('update:modelValue', text.value.split(/\r?\n/));
}

// Normalize only once editing is finished: trim each row and drop blanks.
function normalize() {
    const lines = text.value
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    text.value = lines.join('\n');
    emit('update:modelValue', lines);
}
</script>

<template>
    <div>
        <label class="form-label fw-semibold mb-1">
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>
        <textarea
            :value="text"
            class="form-control"
            :class="{ 'is-invalid': error }"
            :rows="field.rows || 4"
            :maxlength="field.max || undefined"
            @input="onInput"
            @blur="normalize"
        ></textarea>
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="field.help" class="form-text">{{ field.help }}</div>
    </div>
</template>
