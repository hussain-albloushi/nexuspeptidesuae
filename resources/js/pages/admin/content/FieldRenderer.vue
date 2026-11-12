<script setup>
import TextField from './fields/TextField.vue';
import TextareaField from './fields/TextareaField.vue';
import LinesField from './fields/LinesField.vue';
import SelectField from './fields/SelectField.vue';
import IconField from './fields/IconField.vue';
import UploadField from './fields/UploadField.vue';
import RepeaterField from './fields/RepeaterField.vue';
import CurrencyField from './fields/CurrencyField.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Array, Object], default: '' },
    error: { type: String, default: '' },
    uploadUrl: { type: String, required: true },
});

defineEmits(['update:modelValue']);

const components = {
    currency: CurrencyField,
    text: TextField,
    textarea: TextareaField,
    lines: LinesField,
    select: SelectField,
    icon: IconField,
    image: UploadField,
    file: UploadField,
    repeater: RepeaterField,
};

function componentFor(field) {
    return components[field.type] || TextField;
}
</script>

<template>
    <component
        :is="componentFor(field)"
        :field="field"
        :model-value="modelValue"
        :error="error"
        :upload-url="uploadUrl"
        :preview="field.type === 'image'"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
