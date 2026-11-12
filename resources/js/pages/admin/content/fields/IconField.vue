<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

/* Curated set of Font Awesome (Free, solid) icons suitable for storefront
   badge/strip items. Values are stored as full class strings because the
   storefront renders them directly via <i :class="icon">. */
const ICONS = [
    // Quality & trust
    { name: 'Certified seal', value: 'fa-solid fa-seal-check' },
    { name: 'Verified badge', value: 'fa-solid fa-badge-check' },
    { name: 'Award ribbon', value: 'fa-solid fa-award' },
    { name: 'Medal', value: 'fa-solid fa-medal' },
    { name: 'Secure shield', value: 'fa-solid fa-shield-halved' },
    { name: 'Protected', value: 'fa-solid fa-shield-check' },
    { name: 'Certificate', value: 'fa-solid fa-certificate' },
    { name: 'Handshake', value: 'fa-solid fa-handshake' },
    { name: 'Clipboard checked', value: 'fa-solid fa-clipboard-check' },
    { name: 'Checkmark', value: 'fa-solid fa-circle-check' },
    { name: 'Double check', value: 'fa-solid fa-check-double' },

    // Delivery & shipping
    { name: 'Fast delivery', value: 'fa-solid fa-truck-fast' },
    { name: 'Delivery truck', value: 'fa-solid fa-truck' },
    { name: 'Loading truck', value: 'fa-solid fa-truck-ramp-box' },
    { name: 'Parcel', value: 'fa-solid fa-box' },
    { name: 'Open parcel', value: 'fa-solid fa-box-open' },
    { name: 'Stacked boxes', value: 'fa-solid fa-boxes-stacked' },
    { name: 'Warehouse', value: 'fa-solid fa-warehouse' },
    { name: 'Return', value: 'fa-solid fa-rotate-left' },

    // Support & service
    { name: 'Support headset', value: 'fa-solid fa-headset' },
    { name: 'Live chat', value: 'fa-solid fa-comments' },
    { name: 'Phone', value: 'fa-solid fa-phone' },
    { name: 'Email', value: 'fa-solid fa-envelope' },
    { name: 'Help', value: 'fa-solid fa-circle-question' },
    { name: 'Support ring', value: 'fa-solid fa-life-ring' },
    { name: 'Document', value: 'fa-solid fa-file-lines' },

    // Speed & time
    { name: 'Lightning fast', value: 'fa-solid fa-bolt' },
    { name: 'Clock', value: 'fa-solid fa-clock' },
    { name: 'Stopwatch', value: 'fa-solid fa-stopwatch' },
    { name: 'Rocket', value: 'fa-solid fa-rocket' },

    // Payment & pricing
    { name: 'Credit card', value: 'fa-solid fa-credit-card' },
    { name: 'Cash', value: 'fa-solid fa-money-bill-wave' },
    { name: 'Money bag', value: 'fa-solid fa-sack-dollar' },
    { name: 'Discount', value: 'fa-solid fa-percent' },
    { name: 'Price tag', value: 'fa-solid fa-tag' },
    { name: 'Tags', value: 'fa-solid fa-tags' },

    // Research & labs
    { name: 'Flask', value: 'fa-solid fa-flask' },
    { name: 'Lab flask', value: 'fa-solid fa-flask-vial' },
    { name: 'Microscope', value: 'fa-solid fa-microscope' },
    { name: 'DNA', value: 'fa-solid fa-dna' },
    { name: 'Syringe', value: 'fa-solid fa-syringe' },
    { name: 'Vial', value: 'fa-solid fa-vial' },
    { name: 'Vials', value: 'fa-solid fa-vials' },

    // General
    { name: 'Star', value: 'fa-solid fa-star' },
    { name: 'Heart', value: 'fa-solid fa-heart' },
    { name: 'Gem', value: 'fa-solid fa-gem' },
    { name: 'Leaf', value: 'fa-solid fa-leaf' },
    { name: 'Globe', value: 'fa-solid fa-globe' },
    { name: 'Gift', value: 'fa-solid fa-gift' },
    { name: 'Thumbs up', value: 'fa-solid fa-thumbs-up' },
    { name: 'Sparkles', value: 'fa-solid fa-sparkles' },
    { name: 'Trophy', value: 'fa-solid fa-trophy' },
    { name: 'Store', value: 'fa-solid fa-store' },
    { name: 'Happy face', value: 'fa-solid fa-face-smile' },
    { name: 'World', value: 'fa-solid fa-earth-americas' },
    { name: 'Info', value: 'fa-solid fa-circle-info' },
];

/* Font Awesome (Free) brand icons for social links. */
const BRAND_ICONS = [
    { name: 'Instagram', value: 'fa-brands fa-instagram' },
    { name: 'Facebook', value: 'fa-brands fa-facebook' },
    { name: 'X (Twitter)', value: 'fa-brands fa-x-twitter' },
    { name: 'Twitter', value: 'fa-brands fa-twitter' },
    { name: 'YouTube', value: 'fa-brands fa-youtube' },
    { name: 'LinkedIn', value: 'fa-brands fa-linkedin-in' },
    { name: 'TikTok', value: 'fa-brands fa-tiktok' },
    { name: 'Pinterest', value: 'fa-brands fa-pinterest-p' },
    { name: 'Telegram', value: 'fa-brands fa-telegram' },
    { name: 'WhatsApp', value: 'fa-brands fa-whatsapp' },
    { name: 'Snapchat', value: 'fa-brands fa-snapchat' },
    { name: 'Threads', value: 'fa-brands fa-threads' },
    { name: 'Discord', value: 'fa-brands fa-discord' },
    { name: 'GitHub', value: 'fa-brands fa-github' },
    { name: 'Spotify', value: 'fa-brands fa-spotify' },
    { name: 'Twitch', value: 'fa-brands fa-twitch' },
    // Handy non-brand entries often used as social/contact icons
    { name: 'Email', value: 'fa-solid fa-envelope' },
    { name: 'Website link', value: 'fa-solid fa-link' },
    { name: 'RSS feed', value: 'fa-solid fa-rss' },
];

const search = ref('');
const open = ref(false);

const sets = computed(() => props.field.sets || ['solid']);
const searchPlaceholder = computed(() =>
    sets.value.includes('brands')
        ? 'Search icons, for example: instagram, facebook, x, youtube…'
        : 'Search icons, for example: truck, shield, star…',
);

const allIcons = computed(() => {
    const icons = [];

    if (sets.value.includes('brands')) {
        icons.push(...BRAND_ICONS);
    }

    if (sets.value.includes('solid')) {
        icons.push(...ICONS);
    }

    return icons;
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();

    if (!q) {
        return allIcons.value;
    }

    return allIcons.value.filter(
        (icon) => icon.name.toLowerCase().includes(q) || icon.value.toLowerCase().includes(q),
    );
});

const selected = computed(() => allIcons.value.find((icon) => icon.value === props.modelValue) || null);
const isCustom = computed(() => !!props.modelValue && !selected.value);

function choose(icon) {
    emit('update:modelValue', icon.value);
    open.value = false;
    search.value = '';
}

function clear() {
    emit('update:modelValue', '');
    open.value = false;
    search.value = '';
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}
</script>

<template>
    <div @keydown="onKeydown">
        <label class="form-label fw-semibold mb-1">
            {{ field.label }}
            <span v-if="field.required" class="text-danger">*</span>
        </label>

        <!-- Current selection -->
        <div class="d-flex align-items-stretch gap-2">
            <button
                type="button"
                class="form-control d-flex align-items-center gap-2 text-start"
                :class="{ 'is-invalid': error }"
                :aria-expanded="open"
                @click="open = !open"
            >
                <span class="d-inline-flex align-items-center justify-content-center flex-shrink-0 rounded bg-light border" style="width: 34px; height: 34px;">
                    <i v-if="modelValue" :class="modelValue" class="fs-5 text-secondary"></i>
                    <i v-else class="bi bi-question-lg text-secondary"></i>
                </span>
                <span class="flex-grow-1 text-truncate">
                    <span v-if="selected" class="d-block fw-semibold lh-sm">{{ selected.name }}</span>
                    <span v-else-if="isCustom" class="d-block fw-semibold lh-sm">Custom icon</span>
                    <span v-else class="d-block text-secondary">Choose an icon…</span>
                    <span v-if="isCustom" class="d-block small text-secondary text-truncate">{{ modelValue }}</span>
                </span>
                <i class="bi text-secondary" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            </button>
            <button
                v-if="modelValue"
                type="button"
                class="btn btn-outline-secondary"
                title="Remove icon"
                @click="clear"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>

        <!-- Picker panel -->
        <div v-if="open" class="border rounded mt-2 p-2 bg-body-tertiary">
            <div class="position-relative mb-2">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-secondary"></i>
                <input
                    v-model="search"
                    type="text"
                    class="form-control ps-4"
                    :placeholder="searchPlaceholder"
                    aria-label="Search icons"
                >
            </div>

            <div
                v-if="filtered.length"
                class="row g-1"
                style="max-height: 260px; overflow-y: auto;"
            >
                <div v-for="icon in filtered" :key="icon.value" class="col-6 col-sm-4 col-md-3">
                    <button
                        type="button"
                        class="btn w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-1 py-2 px-1 border"
                        :class="icon.value === modelValue ? 'btn-primary border-primary' : 'btn-outline-light text-body'"
                        :title="icon.name"
                        @click="choose(icon)"
                    >
                        <i :class="icon.value" class="fs-5"></i>
                        <span class="small lh-sm text-center">{{ icon.name }}</span>
                    </button>
                </div>
            </div>
            <p v-else class="text-secondary small text-center py-3 mb-0">
                No icons match “{{ search }}”.
            </p>
        </div>

        <div v-if="!error && field.help" class="form-text">{{ field.help }}</div>
    </div>
</template>
