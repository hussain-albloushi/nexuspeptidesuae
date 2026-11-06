<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    uploadUrl: { type: String, required: true },
    preview: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const dragging = ref(false);

const accept = computed(() => (props.preview ? 'image/*' : 'image/*,.pdf'));

const fileName = computed(() => {
    if (!props.modelValue) return '';

    const parts = String(props.modelValue).split('/');

    return decodeURIComponent(parts[parts.length - 1] || 'File');
});

function pick() {
    fileInput.value?.click();
}

function onFile(event) {
    const file = event.target.files?.[0];

    if (file) {
        upload(file);
    }

    event.target.value = '';
}

function onDrop(event) {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file) {
        upload(file);
    }
}

async function upload(file) {
    uploadError.value = '';

    if (file.size > 10 * 1024 * 1024) {
        uploadError.value = 'File must be 10 MB or smaller.';
        return;
    }

    const form = new FormData();
    form.append('file', file);

    uploading.value = true;

    try {
        const { data } = await axios.post(props.uploadUrl, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        emit('update:modelValue', data.url);
    } catch (error) {
        const errors = error?.response?.data?.errors;

        uploadError.value =
            errors?.file?.[0] ||
            error?.response?.data?.message ||
            'Upload failed. Please try again.';
    } finally {
        uploading.value = false;
    }
}

function clear() {
    emit('update:modelValue', '');
}
</script>

<template>
    <div>
        <label class="form-label fw-semibold mb-1">
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>

        <div
            class="d-flex flex-wrap align-items-start gap-3 rounded p-2"
            :class="dragging ? 'border border-primary bg-light' : ''"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div
                v-if="preview && modelValue"
                class="d-flex align-items-center justify-content-center rounded border bg-white p-2"
                style="width: 132px; height: 92px"
            >
                <img
                    :src="modelValue"
                    alt="Preview"
                    style="max-width: 100%; max-height: 100%; object-fit: contain"
                />
            </div>

            <div
                v-else-if="!preview && modelValue"
                class="rounded border bg-white px-3 py-2 small text-truncate"
                style="max-width: 240px"
                :title="fileName"
            >
                <i class="bi bi-file-earmark-text me-1"></i>{{ fileName }}
            </div>

            <div class="flex-grow-1" style="min-width: 260px">
                <div class="input-group">
                    <input
                        class="form-control"
                        :class="{ 'is-invalid': error }"
                        :value="modelValue ?? ''"
                        placeholder="Paste a URL or upload a file"
                        @input="$emit('update:modelValue', $event.target.value)"
                    />
                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        :disabled="uploading"
                        @click="pick"
                    >
                        <span v-if="uploading" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-upload"></i>
                        <span class="d-none d-sm-inline ms-1">Upload</span>
                    </button>
                    <button
                        v-if="modelValue"
                        class="btn btn-outline-danger"
                        type="button"
                        title="Remove"
                        @click="clear"
                    >
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="form-text">
                    {{ field.help || 'JPG, PNG, WebP, GIF, or PDF up to 10 MB. You can also paste a URL.' }}
                </div>
                <div v-if="uploadError" class="text-danger small">{{ uploadError }}</div>
                <div v-else-if="error" class="text-danger small">{{ error }}</div>
            </div>
        </div>

        <input
            ref="fileInput"
            type="file"
            class="d-none"
            :accept="accept"
            @change="onFile"
        />
    </div>
</template>
