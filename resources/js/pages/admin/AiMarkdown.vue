<template>
    <div class="ai-markdown" v-html="html"></div>
</template>

<script setup>
import { computed } from 'vue';
import { renderMarkdown } from './ai.js';

const props = defineProps({ content: { type: String, default: '' } });
const html = computed(() => renderMarkdown(props.content));
</script>

<style scoped>
.ai-markdown :deep(h3),
.ai-markdown :deep(h4),
.ai-markdown :deep(h5) { font-size: 1rem; font-weight: 600; margin: 1rem 0 .5rem; }
.ai-markdown :deep(p) { margin: 0 0 .5rem; }
.ai-markdown :deep(ul) { margin: 0 0 .75rem 1.1rem; padding: 0; }
.ai-markdown :deep(li) { margin-bottom: .25rem; }
.ai-markdown :deep(hr) { margin: 1rem 0; border-color: var(--admin-border); }
.ai-markdown :deep(code) { background: var(--admin-inset); padding: .1rem .3rem; border-radius: 4px; font-size: .82em; }
.ai-markdown :deep(.ai-markdown-table) { font-size: .85rem; margin-bottom: .75rem; }
</style>
