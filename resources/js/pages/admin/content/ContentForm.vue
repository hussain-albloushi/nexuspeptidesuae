<script setup>
import FieldRenderer from './FieldRenderer.vue';

const props = defineProps({
    sections: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    uploadUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

function update(name, value) {
    emit('update:modelValue', { ...props.modelValue, [name]: value });
}

function fieldClass(field) {
    return ['textarea', 'lines', 'repeater'].includes(field.type) ? 'col-12' : 'col-md-6';
}
</script>

<template>
    <div>
        <section v-for="(section, index) in sections" :key="index" class="mb-4">
            <div class="border-bottom mb-3 pb-2">
                <h4 class="h6 mb-1">{{ section.title }}</h4>
                <p v-if="section.description" class="text-secondary small mb-0">
                    {{ section.description }}
                </p>
            </div>

            <div class="row g-3">
                <div
                    v-for="field in section.fields || []"
                    :key="field.name"
                    :class="fieldClass(field)"
                >
                    <FieldRenderer
                        :field="field"
                        :model-value="modelValue[field.name]"
                        :error="errors[field.name]"
                        :upload-url="uploadUrl"
                        @update:model-value="update(field.name, $event)"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
