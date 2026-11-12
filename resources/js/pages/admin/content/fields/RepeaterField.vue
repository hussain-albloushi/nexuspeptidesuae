<script setup>
import { computed } from 'vue';
import { confirmAdmin } from '../../adminAlerts';
import TextField from './TextField.vue';
import TextareaField from './TextareaField.vue';
import LinesField from './LinesField.vue';
import SelectField from './SelectField.vue';
import IconField from './IconField.vue';
import UploadField from './UploadField.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    uploadUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const components = {
    text: TextField,
    textarea: TextareaField,
    lines: LinesField,
    select: SelectField,
    icon: IconField,
    image: UploadField,
    file: UploadField,
};

const items = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));

const canAdd = computed(
    () => !props.field.max_items || items.value.length < props.field.max_items,
);

function componentFor(field) {
    return components[field.type] || TextField;
}

function isWide(field) {
    return ['textarea', 'lines', 'repeater'].includes(field.type);
}

function blankItem() {
    const item = {};

    for (const field of props.field.fields || []) {
        if (field.type === 'select') {
            item[field.name] = field.options?.[0]?.value || '';
        } else if (field.type === 'repeater') {
            item[field.name] = [];
        } else {
            item[field.name] = '';
        }
    }

    return item;
}

function updateItem(index, name, value) {
    emit(
        'update:modelValue',
        items.value.map((item, i) => (i === index ? { ...item, [name]: value } : item)),
    );
}

function add() {
    if (canAdd.value) {
        emit('update:modelValue', [...items.value, blankItem()]);
    }
}

function move(index, direction) {
    const target = index + direction;

    if (target < 0 || target >= items.value.length) {
        return;
    }

    const next = [...items.value];
    [next[index], next[target]] = [next[target], next[index]];
    emit('update:modelValue', next);
}

function duplicate(index) {
    if (!canAdd.value) {
        return;
    }

    const next = [...items.value];
    next.splice(index + 1, 0, JSON.parse(JSON.stringify(items.value[index])));
    emit('update:modelValue', next);
}

async function remove(index) {
    if (!(await confirmAdmin({ title: 'Remove row?', text: 'This cannot be undone after saving.', confirmButtonText: 'Remove row', destructive: true })).isConfirmed) {
        return;
    }

    emit(
        'update:modelValue',
        items.value.filter((_, i) => i !== index),
    );
}

function labelFor(item, index) {
    const value = item?.[props.field.item_label];

    if (!value) {
        return `Row ${index + 1}`;
    }

    const text = String(value);

    return text.length > 52 ? `${text.slice(0, 52)}…` : text;
}
</script>

<template>
    <div>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
            <label class="form-label fw-semibold mb-0">
                {{ field.label }}
                <span v-if="field.required" class="text-danger">*</span>
            </label>
            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                :disabled="!canAdd"
                @click="add"
            >
                <i class="bi bi-plus-lg"></i>
                {{ field.add_label || 'Add row' }}
            </button>
        </div>

        <p v-if="field.help" class="form-text mt-0">{{ field.help }}</p>

        <div
            v-if="!items.length"
            class="rounded border bg-light p-3 text-center text-secondary small mb-2"
        >
            No rows yet. Use “{{ field.add_label || 'Add row' }}” to create one.
        </div>

        <div v-for="(item, index) in items" :key="index" class="rounded border mb-3">
            <div class="d-flex align-items-center gap-2 border-bottom bg-light px-3 py-2">
                <span class="fw-semibold text-truncate me-auto">
                    <i class="bi bi-grip-vertical text-secondary me-1"></i>
                    {{ labelFor(item, index) }}
                </span>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    title="Move up"
                    :disabled="index === 0"
                    @click="move(index, -1)"
                >
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    title="Move down"
                    :disabled="index === items.length - 1"
                    @click="move(index, 1)"
                >
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    title="Duplicate"
                    :disabled="!canAdd"
                    @click="duplicate(index)"
                >
                    <i class="bi bi-copy"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Remove"
                    @click="remove(index)"
                >
                    <i class="bi bi-trash"></i>
                </button>
            </div>

            <div class="p-3">
                <div class="row g-3">
                    <div
                        v-for="sub in field.fields || []"
                        :key="sub.name"
                        :class="isWide(sub) ? 'col-12' : 'col-md-6'"
                    >
                        <component
                            :is="componentFor(sub)"
                            :field="sub"
                            :model-value="item[sub.name]"
                            :upload-url="uploadUrl"
                            :preview="sub.type === 'image'"
                            @update:model-value="updateItem(index, sub.name, $event)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="error" class="text-danger small">{{ error }}</div>
    </div>
</template>
