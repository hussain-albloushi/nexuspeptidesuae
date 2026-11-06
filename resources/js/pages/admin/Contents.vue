<template>
    <div>
        <div v-if="loading" class="card mb-4">
            <div class="card-body text-center py-5">
                <span class="spinner-border text-primary" role="status"></span>
                <p class="text-secondary mt-3 mb-0">Loading content…</p>
            </div>
        </div>

        <div v-else-if="error && !groups.length" class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ error }}
        </div>

        <div v-else class="row g-4">
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Pages</h3>
                    </div>

                    <div class="list-group list-group-flush">
                        <button
                            v-for="group in groups"
                            :key="group.key"
                            type="button"
                            class="list-group-item list-group-item-action d-flex align-items-start gap-2"
                            :class="{ active: group.key === activeKey }"
                            @click="selectGroup(group.key)"
                        >
                            <i class="bi mt-1" :class="group.icon"></i>
                            <span class="flex-grow-1 text-start">
                                <span class="d-block fw-semibold">{{ group.label }}</span>
                                <small :class="group.key === activeKey ? 'text-white-50' : 'text-secondary'">
                                    {{ group.is_active ? 'Published' : 'Draft' }}
                                </small>
                            </span>
                            <i
                                v-if="dirty[group.key]"
                                class="bi bi-circle-fill text-warning mt-1"
                                style="font-size: 0.5rem"
                                title="Unsaved changes"
                            ></i>
                        </button>
                    </div>
                </div>

                <div v-if="dirtyCount" class="alert alert-warning mt-3 py-2 small">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ dirtyCount }} section{{ dirtyCount === 1 ? '' : 's' }} with unsaved changes.
                </div>

                <div class="card mt-3">
                    <div class="card-body small text-secondary">
                        <p class="mb-2"><i class="bi bi-info-circle me-1"></i>How this works</p>
                        <ul class="mb-0 ps-3">
                            <li>Edit a page on the right, then save it.</li>
                            <li>Set a page to <strong>Draft</strong> to hide your edits and keep the current live copy.</li>
                            <li>Upload a logo, image, or PDF and it is stored with the site.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div v-if="activeGroup" class="card">
                    <div class="card-header d-flex flex-wrap align-items-center gap-3">
                        <div class="me-auto">
                            <h3 class="card-title mb-0">{{ activeGroup.label }}</h3>
                            <small class="text-secondary">{{ activeGroup.description }}</small>
                        </div>

                        <a
                            :href="activeGroup.live_url"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-sm btn-outline-secondary"
                        >
                            <i class="bi bi-box-arrow-up-right me-1"></i>
                            Open live page
                        </a>

                        <div class="form-check form-switch mb-0">
                            <input
                                :id="`published-${activeGroup.key}`"
                                v-model="published"
                                class="form-check-input"
                                type="checkbox"
                            />
                            <label class="form-check-label" :for="`published-${activeGroup.key}`">
                                {{ published ? 'Published' : 'Draft' }}
                            </label>
                        </div>
                    </div>

                    <div class="card-body">
                        <div v-if="error" class="alert alert-danger py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ error }}
                        </div>

                        <div v-if="savedMessage" class="alert alert-success py-2">
                            <i class="bi bi-check-circle me-1"></i>{{ savedMessage }}
                        </div>

                        <ContentForm
                            :sections="activeGroup.sections"
                            :model-value="activeDraft.data"
                            :errors="validationErrors"
                            :upload-url="uploadUrl"
                            @update:model-value="updateData"
                        />
                    </div>

                    <div class="card-footer sticky-save d-flex flex-wrap align-items-center gap-3">
                        <span v-if="dirty[activeKey]" class="text-warning fw-semibold">
                            <i class="bi bi-exclamation-circle me-1"></i>Unsaved changes
                        </span>
                        <span v-else-if="activeGroup.updated_at" class="text-secondary small">
                            <i class="bi bi-clock-history me-1"></i>
                            Last saved {{ formatDate(activeGroup.updated_at) }}
                        </span>
                        <span v-else class="text-secondary small">Not edited yet.</span>

                        <button
                            type="button"
                            class="btn btn-primary ms-auto"
                            :disabled="saving"
                            @click="save"
                        >
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-check2-circle me-1"></i>
                            Save changes
                        </button>
                    </div>
                </div>

                <div v-else class="card">
                    <div class="card-body text-center text-secondary py-5">
                        Select a page to start editing.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import ContentForm from './content/ContentForm.vue';

const uploadUrl = '/api/content-editor/upload';

const groups = ref([]);
const activeKey = ref('');
const drafts = ref({});
const original = ref({});
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const savedMessage = ref('');
const validationErrors = ref({});

const activeGroup = computed(
    () => groups.value.find((group) => group.key === activeKey.value) || null,
);

const activeDraft = computed(() => drafts.value[activeKey.value] || null);

const published = computed({
    get: () => Boolean(activeDraft.value?.is_active),
    set: (value) => {
        if (activeDraft.value) {
            activeDraft.value.is_active = value;
        }
    },
});

const dirty = computed(() => {
    const map = {};

    for (const group of groups.value) {
        const draft = drafts.value[group.key];

        if (!draft) {
            continue;
        }

        map[group.key] =
            snapshot(draft) !== original.value[group.key];
    }

    return map;
});

const dirtyCount = computed(() => Object.values(dirty.value).filter(Boolean).length);

function clone(value) {
    return JSON.parse(JSON.stringify(value ?? {}));
}

function snapshot(draft) {
    return JSON.stringify({ data: draft.data, is_active: draft.is_active });
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/api/content-editor');
        groups.value = data.groups || [];

        const nextDrafts = {};
        const nextOriginal = {};

        for (const group of groups.value) {
            const draft = {
                data: clone(group.data),
                is_active: Boolean(group.is_active),
            };

            nextDrafts[group.key] = draft;
            nextOriginal[group.key] = snapshot(draft);
        }

        drafts.value = nextDrafts;
        original.value = nextOriginal;
        activeKey.value = groups.value[0]?.key || '';
    } catch (requestError) {
        error.value =
            requestError?.response?.data?.message ||
            'Could not load content. Please refresh the page and try again.';
    } finally {
        loading.value = false;
    }
}

function selectGroup(key) {
    activeKey.value = key;
    validationErrors.value = {};
    savedMessage.value = '';
    error.value = '';
}

function updateData(value) {
    if (!activeDraft.value) {
        return;
    }

    activeDraft.value.data = value;
    validationErrors.value = {};
    error.value = '';
}

function isEmpty(value) {
    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return value === undefined || value === null || String(value).trim() === '';
}

function validateGroup(group, data) {
    const errors = {};

    for (const section of group.sections || []) {
        for (const field of section.fields || []) {
            const value = data?.[field.name];

            if (field.required && isEmpty(value)) {
                errors[field.name] = `${field.label} is required.`;
                continue;
            }

            if (field.max && typeof value === 'string' && value.length > field.max) {
                errors[field.name] = `${field.label} must be ${field.max} characters or fewer.`;
            }

            if (field.type === 'repeater' && Array.isArray(value)) {
                value.forEach((item, index) => {
                    for (const subField of field.fields || []) {
                        if (subField.required && isEmpty(item?.[subField.name])) {
                            errors[field.name] = `Row ${index + 1} of ${field.label}: ${subField.label} is required.`;
                        }
                    }
                });
            }
        }
    }

    return errors;
}

async function save() {
    const group = activeGroup.value;
    const draft = activeDraft.value;

    if (!group || !draft) {
        return;
    }

    validationErrors.value = validateGroup(group, draft.data);

    if (Object.keys(validationErrors.value).length) {
        error.value = 'Please fix the highlighted fields before saving.';
        savedMessage.value = '';
        return;
    }

    saving.value = true;
    error.value = '';
    savedMessage.value = '';

    try {
        const { data } = await axios.put(`/api/content-editor/${group.key}`, draft);

        group.is_active = data.is_active;
        group.updated_at = data.updated_at;
        group.data = clone(data.data);

        draft.data = clone(data.data);
        draft.is_active = data.is_active;
        original.value[group.key] = snapshot(draft);

        savedMessage.value = 'Saved. Your changes are live on the storefront.';

        window.setTimeout(() => {
            if (savedMessage.value) {
                savedMessage.value = '';
            }
        }, 5000);
    } catch (requestError) {
        const errors = requestError?.response?.data?.errors;

        error.value = errors
            ? Object.values(errors).flat()[0]
            : requestError?.response?.data?.message || 'Could not save changes. Please try again.';
    } finally {
        saving.value = false;
    }
}

function beforeUnload(event) {
    if (!dirtyCount.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => {
    load();
    window.addEventListener('beforeunload', beforeUnload);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
});
</script>

<style scoped>
.sticky-save {
    position: sticky;
    bottom: 0;
    z-index: 2;
}
</style>
