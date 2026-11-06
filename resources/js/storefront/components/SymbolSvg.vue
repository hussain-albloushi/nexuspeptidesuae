<script setup>
/**
 * SymbolSvg — renders the UAE Dirham (AED) currency symbol.
 *
 * Loads the WebP asset from public/storage/500px-UAE_Dirham_Symbol.svg.webp
 * and renders it as an inline SVG. Falls back to the plain-text symbol
 * (default "AED") when the image is unavailable or when the caller
 * explicitly requests a text-only style.
 */

const props = defineProps({
    /** Text shown when the SVG image cannot be rendered. */
    fallback: { type: String, default: 'AED' },
    /** Additional CSS classes applied to the inline SVG element. */
    class: { type: String, default: '' },
    /** Width of the rendered symbol in px. */
    width: { type: [Number, String], default: 20 },
    /** Height of the rendered symbol in px. */
    height: { type: [Number, String], default: 20 },
});

const symbolPath = '/storage/500px-UAE_Dirham_Symbol001.png';
</script>

<template>
    <svg
        :class="props.class"
        :width="props.width"
        :height="props.height"
        viewBox="0 0 500 500"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="AED currency symbol"
    >
        <image
            :href="symbolPath"
            x="0"
            y="0"
            :width="props.width"
            :height="props.height"
            preserveAspectRatio="xMidYMid meet"
        />
        <!-- Fallback text rendered inside the SVG for screen readers and
             browsers that do not support <image> inside inline SVG. -->
        <text
            x="50%"
            y="50%"
            dominant-baseline="central"
            text-anchor="middle"
            font-size="14"
            font-family="sans-serif"
            fill="currentColor"
            class="symbol-fallback"
        >
            {{ props.fallback }}
        </text>
    </svg>
</template>

<style scoped>
/* Hide the fallback text when the WebP image is supported.
   Browsers that can display <image> inside inline SVG will still
   show the WebP; the text remains accessible to screen readers. */
.symbol-fallback {
    display: none;
}
</style>